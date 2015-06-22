<?php
/**
 * @file
 * ------------------------------------------------------------------------------------
 * Created by SAN Business Consultants for RAPTOR phase1 proof of concept
 * Open Source VA Innovation Project 2011-2012
 * Innovator: Dr. Jonathan Medverd
 * SAN Implementation: Andrew Casertano, Frank Font, Frank Smith
 * Contacts: acasertano@sanbusinessconsultants.com, ffont@sanbusinessconsultants.com
 * ------------------------------------------------------------------------------------
 * @author Frank Font
 */

namespace raptor;

if(!defined('__MYFOLDER_CHOICES__')) {
    define('__MYFOLDER_CHOICES__',dirname(__FILE__));
}

require_once (dirname(__FILE__) . '/../core/data_listoptions.php');
require_once ('Choice.php');


/*
 * Configuration
 * @author vhapalfontf
 */
class raptor_datalayer_Choices 
{
    /**
     * Get value from the list.
     * @param string $sPath location of the config file
     * @param string $sID to item match
     * @return string The text associated with the id 
     * @deprecated since version number
     */
    public static function getListItem($sPath,$sFindID,$sAltValue='')
    {
        $z="";
        $aLines = file($sPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach($aLines as $nLine => $sLine)
        {
            if($sLine[0] != '[')
            {
                if(trim($sLine)!='' && $sLine[0] != '#' )
                {
                    $aChoice=explode('|',$sLine);
                    if(count($aChoice)!=2)
                    {
                        die("Improperly configured choices file: $sPath<br>CHECK LINE:$nLine<br>TEXT:$sLine<br>RAW:".print_r($aLines,TRUE));
                    }
                    if($sFindID == $aChoice[1])
                    {
                        return $aChoice[1];
                    } 
                    $z.="|".$aChoice[1];
                    
                }
            }
        }

        return $sAltValue; //.">>$sFindID<<$z>>";
    }
    
    /**
     * Load selection box choices from a text file
     * @param text $sPath Location of the file to load
     * @param text $sDefaultChoiceOverrideID
     * @return \raptor_datalayer_Choice 
     * @deprecated since version number
     */
    public static function getListData($sPath,$sDefaultChoiceOverrideID=NULL,$sDefaultaChoiceText=NULL)
    {
        
        $aLines = file($sPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $sCategory=NULL;
        $aList=array();
        if ($sDefaultChoiceOverrideID !== NULL)
        {
            #TODO - refactor so we select in existing list if it exists there
            if ($sDefaultaChoiceText == NULL)
            {
                $sDefaultaChoiceText=$sDefaultChoiceOverrideID;
            }
            $oC = new raptor_datalayer_Choice($sDefaultaChoiceText,$sDefaultChoiceOverrideID,NULL,TRUE);
            $aList[] = $oC;            
        }

        foreach($aLines as $nLine => $sLine)
        {

            if($sLine[0] == '[')
            {
                //We hit the start of a NEW section.
                $sCategory = substr($sLine,1,strlen($sLine)-2);
            } else {
                //Blank or a comment?
                if(trim($sLine)!='' && $sLine[0] != '#' )
                {
                    $aChoice=explode('|',$sLine);
                    if(count($aChoice)!=2)
                    {
                        throw new \Exception("Improperly configured choices file: $sPath<br>CHECK LINE:"
                                . "$nLine<br>TEXT:$sLine<br>RAW:" 
                                . print_r($aLines,TRUE));
                    }
                    //$oC = new raptor_datalayer_Choice($aChoice[1],$aChoice[0],$sCategory);
                    $oC = new raptor_datalayer_Choice($aChoice[1],$aChoice[1],$sCategory);
                    $aList[] = $oC;
                }
            }

        }
        return $aList;
    }

    public static function getListDataFromArray($aValues,$sDefaultChoiceOverrideID=NULL,$sDefaultaChoiceText=NULL)
    {
        $aList=array();
        if ($sDefaultChoiceOverrideID !== NULL)
        {
            #TODO - refactor so we select in existing list if it exists there
            if ($sDefaultaChoiceText == NULL)
            {
                $sDefaultaChoiceText=$sDefaultChoiceOverrideID;
            }
            $oC = new raptor_datalayer_Choice($sDefaultaChoiceText,$sDefaultChoiceOverrideID,NULL,TRUE);
            $aList[] = $oC;            
        }
        foreach($aValues as $sValue)
        {
            $oC = new raptor_datalayer_Choice($sValue,$sValue,'');
            $aList[] = $oC;
        }
        return $aList;
    }
    
    
    public static function getListItemFromArray($aValues,$sFindID,$sAltValue='')
    {
        foreach($aValues as $sValue)
        {
            if($sFindID == $sValue)
            {
                return $$sValue;
            } 
        }
        return $sAltValue; //.">>$sFindID<<$z>>";
    }
    
    
    public static function getEntericContrastData($sDefaultChoiceOverride
            , &$bFoundInList, $modality_filter=NULL)
    {
        if($modality_filter == NULL)
        {
            $modality_filter = array();
        }
        //TODO -- Cache the instance!!!!!!
        $oLO = new ListOptions();
        $aValues = $oLO->getContrastOptions('ENTERIC', $modality_filter);   //'ANY');
        $aValues[''] = '';  //Add empty option
        if($sDefaultChoiceOverride != NULL)
        {
            $bFoundInList = in_array($sDefaultChoiceOverride, $aValues);
        }
        return raptor_datalayer_Choices::getListDataFromArray($aValues,$sDefaultChoiceOverride);
    }
    
    public static function getIVContrastData($sDefaultChoiceOverride
            , &$bFoundInList, $modality_filter=NULL)
    {
        if($modality_filter == NULL)
        {
            $modality_filter = array();
        }
        //TODO -- Cache the instance!!!!!!
        $oLO = new ListOptions();
        $aValues = $oLO->getContrastOptions('IV', $modality_filter);   //'ANY');
        $aValues[''] = '';  //Add empty option
        if($sDefaultChoiceOverride != NULL)
        {
            $bFoundInList = in_array($sDefaultChoiceOverride, $aValues);
        }
        return raptor_datalayer_Choices::getListDataFromArray($aValues,$sDefaultChoiceOverride);
    }
    
    public static function getEntericRadioisotopeData($sDefaultChoiceOverride, &$bFoundInList, $modality_filter=NULL)
    {
        if($modality_filter == NULL)
        {
            $modality_filter = array();
        }

        //TODO -- Cache the instance!!!!!!
        $oLO = new ListOptions();
        $aValues = $oLO->getRadioisotopeOptions('ENTERIC', 'ANY');
        $aValues[''] = '';  //Add empty option
        if($sDefaultChoiceOverride != NULL)
        {
            $bFoundInList = in_array($sDefaultChoiceOverride, $aValues);
        }
        return raptor_datalayer_Choices::getListDataFromArray($aValues,$sDefaultChoiceOverride);
    }
    
    public static function getIVRadioisotopeData($sDefaultChoiceOverride, &$bFoundInList, $modality_filter=NULL)
    {
        if($modality_filter == NULL)
        {
            $modality_filter = array();
        }

        //TODO -- Cache the instance!!!!!!
        $oLO = new ListOptions();
        $aValues = $oLO->getRadioisotopeOptions('IV', 'ANY');
        $aValues[''] = '';  //Add empty option
        if($sDefaultChoiceOverride != NULL)
        {
            $bFoundInList = in_array($sDefaultChoiceOverride, $aValues);
        }
        return raptor_datalayer_Choices::getListDataFromArray($aValues,$sDefaultChoiceOverride);
    }

    public static function getEntericRadioisotopeMatch($sID)
    {
        $oLO = new ListOptions();   //TODO -- Cache the instance!!!!!!
        $aValues = $oLO->getRadioisotopeOptions('ENTERIC', 'ANY');
        $aValues[''] = '';  //Add empty option
        return raptor_datalayer_Choices::getListItemFromArray($aValues, $sID);
    }
    
    public static function getOralHydrationData($sDefaultChoiceOverride, &$bFoundInList, $modality_filter=NULL)
    {
        if($modality_filter == NULL)
        {
            $modality_filter = array();
        }

        //TODO -- Cache the instance!!!!!!
        $oLO = new ListOptions();
        $aValues = $oLO->getHydrationOptions('ORAL', 'ANY');
        $aValues[''] = '';  //Add empty option
        if($sDefaultChoiceOverride != NULL)
        {
            $bFoundInList = in_array($sDefaultChoiceOverride, $aValues);
        }
        return raptor_datalayer_Choices::getListDataFromArray($aValues,$sDefaultChoiceOverride);
    }

    public static function getIVHydrationData($sDefaultChoiceOverride, &$bFoundInList, $modality_filter=NULL)
    {
        if($modality_filter == NULL)
        {
            $modality_filter = array();
        }

        //TODO -- Cache the instance!!!!!!
        $oLO = new ListOptions();
        $aValues = $oLO->getHydrationOptions('IV', 'ANY');
        $aValues[''] = '';  //Add empty option
        if($sDefaultChoiceOverride != NULL)
        {
            $bFoundInList = in_array($sDefaultChoiceOverride, $aValues);
        }
        return raptor_datalayer_Choices::getListDataFromArray($aValues,$sDefaultChoiceOverride);
    }
    
    public static function getOralSedationData($sDefaultChoiceOverride, &$bFoundInList, $modality_filter=NULL)
    {
        if($modality_filter == NULL)
        {
            $modality_filter = array();
        }

        $oLO = new ListOptions();
        $aValues = $oLO->getSedationOptions('ORAL', 'ANY');
        $aValues[''] = '';  //Add empty option
        $bFoundInList = FALSE;
        if($sDefaultChoiceOverride != NULL)
        {
            $bFoundInList = in_array($sDefaultChoiceOverride, $aValues);
        }
        return raptor_datalayer_Choices::getListDataFromArray($aValues,$sDefaultChoiceOverride);
    }

    public static function getIVSedationData($sDefaultChoiceOverride, &$bFoundInList, $modality_filter=NULL)
    {
        if($modality_filter == NULL)
        {
            $modality_filter = array();
        }

        $oLO = new ListOptions();
        $aValues = $oLO->getSedationOptions('IV', 'ANY');
        $aValues[''] = '';  //Add empty option
        if($sDefaultChoiceOverride != NULL)
        {
            $bFoundInList = in_array($sDefaultChoiceOverride, $aValues);
        }
        return raptor_datalayer_Choices::getListDataFromArray($aValues,$sDefaultChoiceOverride);
    }    

    public static function getIVRadioisotopeMatch($sID)
    {
        $oLO = new ListOptions();   //TODO -- Cache the instance!!!!!!
        $aValues = $oLO->getRadioisotopeOptions('IV', 'ANY');
        $aValues[''] = '';  //Add empty option
        return raptor_datalayer_Choices::getListItemFromArray($aValues, $sID);
    }    
    
    public static function getEntericContrastMatch($sID)
    {
        $oLO = new ListOptions();   //TODO -- Cache the instance!!!!!!
        $aValues = $oLO->getContrastOptions('ENTERIC', 'ANY');
        $aValues[''] = '';  //Add empty option
        return raptor_datalayer_Choices::getListItemFromArray($aValues, $sID);
    }
    
    public static function getIVContrastMatch($sID)
    {
        $oLO = new ListOptions();   //TODO -- Cache the instance!!!!!!
        $aValues = $oLO->getContrastOptions('IV', 'ANY');
        $aValues[''] = '';  //Add empty option
        return raptor_datalayer_Choices::getListItemFromArray($aValues, $sID);
    }
    
    public static function getOralHydrationMatch($sID)
    {
        $oLO = new ListOptions();   //TODO -- Cache the instance!!!!!!
        $aValues = $oLO->getHydrationOptions('ORAL', 'ANY');
        $aValues[''] = '';  //Add empty option
        return raptor_datalayer_Choices::getListItemFromArray($aValues, $sID);
    }
    
    public static function getIVHydrationMatch($sID)
    {
        $oLO = new ListOptions();   //TODO -- Cache the instance!!!!!!
        $aValues = $oLO->getHydrationOptions('IV', 'ANY');
        $aValues[''] = '';  //Add empty option
        return raptor_datalayer_Choices::getListItemFromArray($aValues, $sID);
    }
    
    public static function getOralSedationMatch($sID)
    {
        $oLO = new ListOptions();   //TODO -- Cache the instance!!!!!!
        $aValues = $oLO->getSedationOptions('ORAL', 'ANY');
        $aValues[''] = '';  //Add empty option
        return raptor_datalayer_Choices::getListItemFromArray($aValues, $sID);
    }
    
    public static function getIVSedationMatch($sID)
    {
        $oLO = new ListOptions();   //TODO -- Cache the instance!!!!!!
        $aValues = $oLO->getSedationOptions('IV', 'ANY');
        $aValues[''] = '';  //Add empty option
        return raptor_datalayer_Choices::getListItemFromArray($aValues, $sID);
    }

    
    public static function getServicesData($sDefaultChoiceOverride=NULL)
    {
        return array(); //Return an empty result for now.
    }    
    
}

