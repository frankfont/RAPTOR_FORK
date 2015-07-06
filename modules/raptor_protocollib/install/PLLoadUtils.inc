<?php
/**
 * ------------------------------------------------------------------------------------
 * Created by SAN Business Consultants for RAPTOR phase 2
 * Open Source VA Innovation Project 2011-2015
 * VA Innovator: Dr. Jonathan Medverd
 * SAN Implementation: Andrew Casertano, Frank Font, et al
 * Contacts: acasertano@sanbusinessconsultants.com, ffont@sanbusinessconsultants.com
 * ------------------------------------------------------------------------------------
 *  
 */

namespace raptor;

/**
 * This class is for protocol library database schema content
 *
 * @author Frank Font of SAN Business Consultants
 */
class PLLoadUtils
{

    public static function getFileAsArray($sFilename)
    {
        $sPath = RAPTOR_PL_MODULE_PATH.'/install/'.$sFilename;
        $aLines = file($sPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return $aLines;
    }    
    
    public static function loadProtocolLibBasedata($clearall=FALSE)
    {
        $aLines = self::getFileAsArray('load_demoprotocollib.raw');
        $aProtocols=array();
        $aTables=array();
        $sTablename=NULL;
        $nRows = NULL;
        $aData=NULL;
        foreach($aLines as $row)
        {
            if(substr($row,0,13) == '>>>tablename=')
            {
                $sTablename = substr($row,13);
                $aTables[] = $sTablename;
            } else
            if(substr($row,0,8) == '>>>rows=')
            {
                $nRows = intval(substr($row,8));
            } else
            if($nRows !== NULL && $nRows > 0 && substr($row,0,8) == '>>>data=')
            {
                $aData = unserialize(substr($row,8));
                if($sTablename == 'raptor_protocol_lib')
                {
                    foreach($aData as $aRow)
                    {
                        $protocol_shortname = trim($aRow->protocol_shortname);
                        $aProtocols[] = $protocol_shortname;
                    }
                }
            }
        }
        if($clearall)
        {
            //Clear all the tables declared in the file.
            foreach($aTables as $sTable)
            {
                db_delete($sTable)
                        ->execute();
            }
        }

        //Now load all the tables
        $sTablename = NULL;
        $nRows=NULL;
        $aData=NULL;
        $rowsloaded = array();
        foreach($aLines as $row)
        {
            if(substr($row,0,13) == '>>>tablename=')
            {
                $sTablename = substr($row,13);
            } else
            if(substr($row,0,8) == '>>>rows=')
            {
                $nRows = intval(substr($row,8));
            } else
            if($nRows !== NULL && $nRows > 0 && substr($row,0,8) == '>>>data=')
            {
                $aData = unserialize(substr($row,8));
                if($sTablename !== NULL)
                {
                    $rowsloaded[$sTablename] = 0;
                    $newuid = NULL;
                    $fields = array();
                    foreach($aData as $aRow)
                    {
                        foreach($aRow as $fieldname => $value)
                        {
                            $fields[$fieldname] = $value;
                        }
                        $inserted = db_insert($sTablename)
                                    ->fields($fields)
                                    ->execute();
                        $rowsloaded[$sTablename] ++;
                    }                
                }
                $sTablename = NULL; //Now clear it.
            }
        }
        $showtableinfo = array();
        foreach($rowsloaded as $sTablename=>$rowcount)
        {
            $showtableinfo[] = "$sTablename inserted $rowcount rows";
        }
        drupal_set_message('Loaded '.count($aTables).' protocollib tables<ol><li>'.implode('<li>',$showtableinfo).'</ol>');
    }
}
