<?php
/**
 * @file
 * ------------------------------------------------------------------------------------
 * Created by SAN Business Consultants for RAPTOR phase 2
 * Open Source VA Innovation Project 2011-2014
 * VA Innovator: Dr. Jonathan Medverd
 * SAN Implementation: Andrew Casertano, Frank Font, et al
 * Contacts: acasertano@sanbusinessconsultants.com, ffont@sanbusinessconsultants.com
 * ------------------------------------------------------------------------------------
 * 
 */ 

namespace raptor;

require_once (RAPTOR_GLUE_MODULE_PATH . '/functions/protocol.inc');

module_load_include('php', 'raptor_datalayer', 'config/Choices');

require_once 'FormHelper.php';
require_once 'ProtocolLibPageHelper.php';
require_once 'ChildEditBasePage.php';

/**
 * This class returns the Admin Information input content
 *
 * @author Frank Font of SAN Business Consultants
 */
class AddProtocolLibPage extends \raptor\ChildEditBasePage
{
    private $m_oPageHelper = null;
    
     //Call same function as in EditUserPage here!
    function __construct()
    {
        $this->m_oPageHelper = new \raptor\ProtocolLibPageHelper();
        global $base_url;
        $this->setGobacktoURL($base_url.'/raptor/manageprotocollib');
    }

    /**
     * Get the values to populate the form.
     * @return type result of the queries as an array
     */
    function getFieldValues()
    {
        $myvalues = $this->m_oPageHelper->getFieldValues(null);
        $myvalues['DefaultValues'] = null;
        $myvalues['protocol_shortname'] = null;
        $myvalues['name'] = null;
        $myvalues['version'] = 1;
        $myvalues['modality_abbr'] = null;
        $myvalues['active_yn'] = 1;
        $myvalues['service_nm'] = null;
        $myvalues['lowerbound_weight'] = null;
        $myvalues['upperbound_weight'] = null;
        $myvalues['image_guided_yn'] = 0;
        $myvalues['contrast_yn'] = 0;
        $myvalues['radioisotope_yn'] = 0;
        $myvalues['sedation_yn'] = 0;
        $myvalues['yn_attribs'] = array('IG'=>$myvalues['image_guided_yn']
                ,'C'=>$myvalues['contrast_yn']
                ,'RI'=>$myvalues['radioisotope_yn']
                ,'S'=>$myvalues['sedation_yn']);
        $myvalues['filename'] = null;
        $myvalues['protocolnotes_tx'] = NULL;        
        return $myvalues;
    }

    /**
     * Validate the proposed values.
     * @param type $form
     * @param type $myvalues
     * @return true if no validation errors detected
     */
    function looksValidFormState($form, &$form_state)
    {
        return $this->m_oPageHelper
                ->looksValidFormState($form, $form_state, 'A');
    }
    
    /**
     * Write the values into the database.
     */
    function updateDatabase($form, $myvalues)
    {
        $bHappy = TRUE; //Assume no problems.

        //drupal_set_message('>>>myvalues>>>'.print_r($myvalues,TRUE));

        //Perform some data quality checks now.
        if(!isset($myvalues['protocol_shortname']) 
                || trim($myvalues['protocol_shortname']) == '')
        {
            throw new \Exception("Cannot insert record because"
                    . " missing protocol_shortname in array!\n" 
                    . print_r($myvalues,TRUE));
        }

        $protocol_shortname = $myvalues['protocol_shortname'];

        if(!isset($myvalues['protocolfile']) || $myvalues['protocolfile'] == '')
        {
            $file=NULL;
            $rawfilename=NULL;
            $newfilename=NULL;
            $filetype=NULL;
            $filesize=NULL;
        } else {
            $file=$myvalues['protocolfile'];
            $rawfilename = $file->filename;
            $fileinfo = pathinfo($rawfilename);
            $newfilename = $protocol_shortname.'-v'.$myvalues['version']
                    .'.'.$fileinfo['extension'];
            $filetype = strtoupper($fileinfo['extension']);
            $filesize=234;  //TODO
        }
        
        global $user;
        $updated_dt = date("Y-m-d H:i", time());
        try
        {
            //Setup key file upload details
            $filename = $newfilename;
            $original_filename = $rawfilename;
            $original_file_upload_dt = $updated_dt;
            $original_file_upload_by_uid = $user->uid;
            $myvalues['upload_file_now'] = TRUE;

            //Important that we add these into the myvalues array so they get into other handlers
            $myvalues['filetype'] = $filetype;
            $myvalues['filesize'] = $filesize;
            $myvalues['filename'] = $filename;
            $myvalues['original_filename'] = $original_filename;
            $myvalues['original_file_upload_dt'] = $original_file_upload_dt;
            $myvalues['original_file_upload_by_uid'] = $original_file_upload_by_uid;

            //Prepare the values
            $yn_attribs = isset($myvalues['yn_attribs']) ? $myvalues['yn_attribs'] : array();
            $contrast_yn = (isset($yn_attribs['C']) && $yn_attribs['C'] === 'C') ? 1 : 0;
            $image_guided_yn = (isset($yn_attribs['IG']) && $yn_attribs['IG'] === 'IG') ? 1 : 0;
            $radioisotope_yn = (isset($yn_attribs['RI']) && $yn_attribs['RI'] === 'RI') ? 1 : 0;
            $sedation_yn = (isset($yn_attribs['S']) && $yn_attribs['S'] === 'S') ? 1 : 0;
            $lbw = (isset($myvalues['lowerbound_weight']) 
                    && is_numeric($myvalues['lowerbound_weight']) 
                    ? $myvalues['lowerbound_weight'] : 0);
            $ubw = (isset($myvalues['upperbound_weight']) 
                    && is_numeric($myvalues['upperbound_weight']) 
                    ? $myvalues['upperbound_weight'] : 0);
            $active_yn = 1;
            $multievent_yn = 0;
            db_insert('raptor_protocol_lib')
              ->fields(array(
                'protocol_shortname' => $protocol_shortname,
                'name' => $myvalues['name'],
                'version' => $myvalues['version'],    
                'modality_abbr' => $myvalues['modality_abbr'],
                'service_nm' => '', //hardcoded as empty string always for now $myvalues['service_nm'],
                'lowerbound_weight' => $lbw,
                'upperbound_weight' => $ubw,
                'image_guided_yn' => $image_guided_yn,     
                'sedation_yn' => $sedation_yn,         
                'contrast_yn' => $contrast_yn,         
                'radioisotope_yn' => $radioisotope_yn,         
                'multievent_yn' => $multievent_yn,         
              'filename' => $filename,
              'original_filename' => $original_filename,
              'original_file_upload_dt' => $original_file_upload_dt,
              'original_file_upload_by_uid' => $original_file_upload_by_uid,
                'active_yn' => $active_yn,
                'updated_dt' => $updated_dt,
                ))
                  ->execute(); 

              //Now write all the child records.
              $bHappy = $this->m_oPageHelper->writeChildRecords($protocol_shortname, $myvalues);
        }
        catch(\Exception $ex)
        {
          error_log("code=".$ex->getCode()
                  ."\nFailed to add protocol into database \n" 
                  . print_r($myvalues, TRUE) . '>>>'. print_r($ex, TRUE));
          drupal_set_message('Failed to add the new protocol because ' 
                  . $ex, 'error');
          return 0;
        }
        
        //Upload the scanned file too if there was one.
        if($bHappy && isset($myvalues['protocolfile']) 
                && $myvalues['protocolfile'] != NULL)
        {
            $file=$myvalues['protocolfile'];
            //unset($form_state['values']['file']);
            $file->status = FILE_STATUS_PERMANENT;
            file_save($file);
            drupal_set_message(t('The form has been submitted and the image has been saved, filename: @filename.'
                    , array('@filename' => $file->filename)));            
            
            $source_uri = 'public://'.$file->filename;
            $dest_uri = 'public://library/'.$newfilename;
            file_unmanaged_copy($source_uri, $dest_uri);

            drupal_set_message('Saved new protocol and uploaded file for ' . $protocol_shortname);
        } else if($bHappy) {
            //Returns 1 if everything was okay
            drupal_set_message('Saved new protocol ' . $protocol_shortname);
        } else {
            $bHappy = false;
            drupal_set_message('Trouble saving new protocol ' . $protocol_shortname, 'warning');
        }
      
        return $bHappy;
    }


    /**
     * @return array of all option values for the form
     */
    function getAllOptions()
    {
        return $this->m_oPageHelper->getAllOptions();
    }
    
    /**
     * Get all the form contents for rendering
     * @return type renderable array
     */
    function getForm($form, &$form_state, $disabled, $myvalues)
    {
        $form = $this->m_oPageHelper->getForm('A',$form, $form_state, FALSE, $myvalues, 'protocol_container_styles');

        $protocol_shortname = '';
        
       //Replace the buttons
       $form['data_entry_area1']['action_buttons'] = array(
            '#type' => 'item', 
            '#prefix' => '<div class="raptor-action-buttons">',
            '#suffix' => '</div>', 
            '#tree' => TRUE,
        );
        $form['data_entry_area1']['action_buttons']['create'] = array('#type' => 'submit'
                , '#attributes' => array('class' => array('admin-action-button'), 'id' => 'admin-action-button-add')
                , '#validate' => array('raptor_glue_addprotocollib_form_builder_customvalidate')
                , '#value' => t('Save New Protocol'));
 
        global $base_url;
        $worklist_url = $base_url . '/worklist';
        $goback = $this->getGobacktoFullURL();
        $form['data_entry_area1']['action_buttons']['cancel'] = array('#type' => 'item'
                , '#markup' => '<input class="admin-cancel-button" id="user-cancel"'
                . ' type="button" value="Cancel"'
                . ' data-redirect="'.$goback.'">');
        
        return $form;
    }
}