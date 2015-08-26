<?php
/**
 * @file
 * ------------------------------------------------------------------------------------
 * Created by SAN Business Consultants for RAPTOR phase 2
 * Open Source VA Innovation Project 2011-2015
 * VA Innovator: Dr. Jonathan Medverd
 * SAN Implementation: Andrew Casertano, Frank Font, Alex Podlesny, et al
 * EWD Integration and VISTA collaboration: Joel Mewton, Rob Tweed
 * Contacts: acasertano@sanbusinessconsultants.com, ffont@sanbusinessconsultants.com
 * ------------------------------------------------------------------------------------
 * Copyright 2015 SAN Business Consultants, a Maryland USA company (sanbusinessconsultants.com)
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * ------------------------------------------------------------------------------------
 */ 

namespace raptor_ewdvista;

require_once 'IEwdDao.php';
require_once 'EwdUtils.php';
require_once 'WebServices.php';
require_once 'WorklistHelper.php';
require_once 'DashboardHelper.php';
require_once 'NotesHelper.php';
require_once 'VitalsHelper.php';
require_once 'MedicationHelper.php';

defined('VERSION_INFO_RAPTOR_EWDDAO')
    or define('VERSION_INFO_RAPTOR_EWDDAO', 'EWD VISTA EHR Integration 20150826.1');

defined('REDAO_CACHE_NM_WORKLIST')
    or define('REDAO_CACHE_NM_WORKLIST', 'getWorklistDetailsMapData');
defined('REDAO_CACHE_NM_SUFFIX_DASHBOARD')
    or define('REDAO_CACHE_NM_SUFFIX_DASHBOARD', '_getDashboardDetailsMapEWD');
defined('REDAO_CACHE_NM_SUFFIX_VITALS')
    or define('REDAO_CACHE_NM_SUFFIX_VITALS', '_getRawVitalSignsMapEWD');

/**
 * This is the primary interface implementation to VistA using EWDJS
 *
 * @author Frank Font of SAN Business Consultants
 */
class EwdDao implements \raptor_ewdvista\IEwdDao
{
    private $m_groupname = 'EwdDaoGroup';
    private $m_createdtimestamp = NULL;
    private $m_oWebServices = NULL;
    private $m_worklistHelper = NULL;
    private $m_dashboardHelper = NULL;
    private $m_info_message = NULL;
    private $m_session_key_prefix = NULL;
    
    public function __construct($session_key_prefix='EWDDAO')
    {
        $this->m_session_key_prefix = $session_key_prefix;
        
        module_load_include('php', 'raptor_datalayer', 'core/Context');
        module_load_include('php', 'raptor_datalayer', 'core/RuntimeResultFlexCache');
        $this->m_createdtimestamp = microtime();        
        $this->m_oWebServices = new \raptor_ewdvista\WebServices();
        $this->m_worklistHelper = new \raptor_ewdvista\WorklistHelper();
        $this->m_dashboardHelper = new \raptor_ewdvista\DashboardHelper();
        $this->initClient();
    }

    public function getIntegrationInfo()
    {
        return VERSION_INFO_RAPTOR_EWDDAO;
    }

    /**
     * Set the instance info message.  
     */
    public function setCustomInfoMessage($msg)
    {
        $this->m_info_message = $msg;
    }
    
    /**
     * Get the instance info message.
     */
    public function getCustomInfoMessage()
    {
        return $this->m_info_message;
    }
    
    
    private function endsWith($string, $test) 
    {
        $strlen = strlen($string);
        $testlen = strlen($test);
        if ($testlen > $strlen) 
        {
            return FALSE;
        }
        return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
    }
    
    /**
     * Return the site specific fully qualified URL for the service.
     */
    private function getURL($servicename,$args=NULL)
    {
        $base_ewdfed_url = trim(EWDFED_BASE_URL);
        if(!$this->endsWith($base_ewdfed_url,'/'))
        {
           error_log("TUNING TIP: Add missing '/' at the end of the EWDFED_BASE_URL declaration (Currently declared as '$base_ewdfed_url')");
           $base_ewdfed_url .= '/';
        }
        if($args === NULL)
        {
            return $base_ewdfed_url . "$servicename";
        } else {
            $argtext = '';
            foreach($args as $k=>$v)
            {
                if($argtext > '')
                {
                    $argtext .= '&';
                }
                $encoded = urlencode($v);
                $argtext .= "$k=$encoded";
            }
            return $base_ewdfed_url . "$servicename?{$argtext}";
        }
    }
    
    /**
     * Initialize the DAO client session
     */
    private function initClient()
    {
        try
        {
            error_log('Starting EWD initClient at ' . microtime(TRUE));
            $this->disconnect();    //Clear all session variables
            $servicename = 'initiate';
            $url = $this->getURL($servicename);
            //$json_string = $this->m_oWebServices->callAPI($servicename, $url);
            $json_string = $this->m_oWebServices->callAPI('GET', $url);
            $json_array = json_decode($json_string, TRUE);
            $this->setSessionVariable('authorization',trim($json_array["Authorization"]));
            $this->setSessionVariable('init_key',trim($json_array["key"]));
            $authorization = $this->getSessionVariable('authorization');
            if($authorization == '')
            {
                throw new \Exception("Missing authorization value in result! [URL: $url]"
                        . "\n >>> array result=".print_r($json_array,TRUE) 
                        . "\n >>> raw JSON=".print_r($json_string,TRUE)
                        . "\n >>> urlencoded JSON=".  urlencode($json_string)
                        . "\n");    //So that the rest of the exception is not blanded into this line!
            }
            $init_key = $this->getSessionVariable('init_key');
            if($init_key == '')
            {
                throw new \Exception("Missing init key value in result! [URL: $url]"
                        . "\n >>> array result=".print_r($json_array,TRUE) 
                        . "\n >>> raw JSON=".print_r($json_string,TRUE)
                        . "\n >>> urlencoded JSON=".  urlencode($json_string)
                        . "\n");    //So that the rest of the exception is not blanded into this line!
            }
            error_log('EWD initClient is DONE at ' . microtime(TRUE));
        } catch (\Exception $ex) {
            throw new \Exception('Trouble in initClient because ' . $ex , 99876 , $ex);
        }
    }

    /**
     * Return TRUE if already authenticated
     */
    public function isAuthenticated() 
    {
        $userduz = $this->getSessionVariable('userduz');
        return ($userduz != NULL);
    }

    private function setSessionVariable($name,$value)
    {
        $fullname = "{$this->m_session_key_prefix}_$name";
        $_SESSION[$fullname] = $value;
    }

    private function getSessionVariable($name)
    {
        $fullname = "{$this->m_session_key_prefix}_$name";
        if(isset($_SESSION[$fullname]) 
                && $_SESSION[$fullname] > '')
        {
            return $_SESSION[$fullname];
        }
        return NULL;
    }
    
    /**
     * Disconnect this DAO from a session
     */
    public function disconnect() 
    {
        $this->setSessionVariable('userduz',NULL);
        $this->setSessionVariable('authorization',NULL);
        $this->setSessionVariable('init_key', NULL);
        $this->setSessionVariable('credentials', NULL);
        $this->setSessionVariable('dt', NULL);
        $this->setSessionVariable('displayname', NULL);
        $this->setSessionVariable('fullname', NULL);
        $this->setSessionVariable('greeting', NULL);
        $this->setPatientID(NULL);
    }

    /**
     * Attempt to login and mark the user authenticated
     */
    public function connectAndLogin($siteCode, $username, $password) 
    {
        try
        {
            error_log('Starting EWD connectAndLogin at ' . microtime());
            $errorMessage = "";
            
            //Are we already logged in?
            if($this->isAuthenticated())
            {
                //Log out before we try again!
                $this->disconnect();
            }
            
            //Have we already initialized the client?
            $authorization = $this->getSessionVariable('authorization');
            if($authorization == NULL)
            {
                //Initialize it now
                error_log("Calling init from connectAndLogin for $this");
                $this->initClient();
                $authorization = $this->getSessionVariable('authorization');
            }
            $init_key = $this->getSessionVariable('init_key');
            if($init_key == NULL)
            {
                throw new \Exception("No initialization key has been set!");
            }
            module_load_include('php', 'raptor_ewdvista', 'core/Encryption');
            $encryption = new \raptor_ewdvista\Encryption();
            $credentials = $encryption->getEncryptedCredentials($init_key, $username, $password);
            $this->setSessionVariable('credentials', $credentials);

            $method = 'login';
            //http://localhost:8081/RaptorEwdVista/raptor/login?credentials=
            $url = $this->getURL($method) . "?credentials=" . $credentials;
            $header["Authorization"]=$authorization;
            
            //error_log("LOOK header>>>".print_r($header,TRUE));
            //error_log("LOOK url>>>".print_r($url,TRUE));
            
            $json_string = $this->m_oWebServices->callAPI('GET', $url, FALSE, $header);            
            $json_array = json_decode($json_string, TRUE);
            
            if (array_key_exists("DUZ", $json_array))
            {
                $this->setSessionVariable('dt',trim($json_array['DT']));
                $this->setSessionVariable('userduz',trim($json_array['DUZ']));
                $this->setSessionVariable('displayname',trim($json_array['displayName']));
                $this->setSessionVariable('fullname',trim($json_array['username']));
                $this->setSessionVariable('greeting',trim($json_array['greeting']));
            }
            else {
                $errorMessage = "Unable to LOGIN because missing DUZ in " . print_r($json_array, TRUE);
                throw new \Exception($errorMessage);
            }
        } catch (\Exception $ex) {
            $thecreds = $this->getSessionVariable('credentials');
            $this->disconnect();
            throw new \Exception("Trouble in connectAndLogin at $siteCode as $username with cred={$thecreds} because ".$ex,99876,$ex);
        }
    }

    /**
     * Return the raw result from the restful service.
     */
    private function getServiceRelatedData($serviceName,$args=NULL)
    {
        try
        {
            //error_log("Starting EWD $serviceName at " . microtime(TRUE));
            $url = $this->getURL($serviceName,$args);
            $authorization = $this->getSessionVariable('authorization');
            if($authorization == NULL)
            {
                throw new \Exception("Missing the authorization string in call to $serviceName");
            }
            $header["Authorization"]=$authorization;
            
            $json_string = $this->m_oWebServices->callAPI('GET', $url, FALSE, $header);            
        error_log("LOOK JSON DATA for GET@URL=$url has result = " . print_r($json_string, TRUE));
            $php_array = json_decode($json_string, TRUE);
            
            //error_log("Finish EWD $serviceName at " . microtime(TRUE));
            return $php_array;
        } catch (\Exception $ex) {
            throw new \Exception("Trouble with $serviceName($args) because $ex", 99876, $ex);;
        }
    }
    
    /**
    * http://stackoverflow.com/questions/190421/caller-function-in-php-5
    */
    private function getCallingFunctionName($completeTrace=FALSE)
    {
        $trace=debug_backtrace();
        $functionName = "";
        if($completeTrace)
        {
            $str = '';
            foreach($trace as $caller)
            {
                //get the name, and we really interested in the last name in the wholepath 
                $functionName = "".$caller['function'];
                //get log information    
                $str .= " -- Called by {$caller['function']}";
                if (isset($caller['class']))
                {
                    $str .= " From Class {$caller['class']}";
                }
            }
        }
        else
        {
            //$caller=$trace[2];  20150812 Not safe to hardcode key as 2; does not always work!
            $breakatnext = FALSE;
            foreach($trace as $key=>$caller)
            {
                $functionName = "".$caller['function'];
                if($breakatnext)
                {
                    break;
                } else
                if($functionName == 'getCallingFunctionName')
                {
                    $breakatnext = TRUE;
                }
            }
            if(!$breakatnext)
            {
                throw new \Exception("Failed to find the calling function name in ".print_r($trace,TRUE));
            }
            $functionName = "".$caller['function'];
            $str = "Called by {$functionName}";
            if (isset($caller['class']))
            {
                $str .= " From Class {$caller['class']}";
            }
        }
        //error_log("LOOK getCallingFunctionName: " . $str);
        return $functionName;
    }
    
    /**
     * Returns array of arrays the way RAPTOR expects it.
     */
    public function getWorklistDetailsMap($max_rows_one_call = 1500, $start_with_IEN=NULL)
    {
        try
        {
            $args = array();
            $serviceName = $this->getCallingFunctionName();
            if($start_with_IEN == NULL)
            {
                $start_from_IEN = '';
            } else {
                if(!is_numeric($start_with_IEN))
                {
                    throw new \Exception("The starting IEN declaration must be numeric but instead we got ".print_r($start_with_IEN,TRUE));
                }
                $start_from_IEN = intval($start_with_IEN) + 1; //So we really start there
            }
            $maxpages=1;
            $pages=0;
            $matching_offset=NULL;
            $getmorepages = TRUE;
            $show_rows = array();
            $pending_orders_map = array();
            $args['max'] = $max_rows_one_call;
            $row_bundles = array();
            while($getmorepages)
            {
                $pages++;
                $args['from'] = $start_from_IEN;    //VistA starts from this value -1!!!!!
                $rawdatarows = $this->getServiceRelatedData($serviceName, $args);
//error_log("LOOK raw data rows for worklist>>>>".print_r($rawdatarows, TRUE));            
                $bundle = $this->m_worklistHelper->getFormatWorklistRows($rawdatarows);
                $formated_datarows = $bundle['all_rows'];
                $rowcount = count($formated_datarows);
                if($rowcount == 0 || !isset($bundle['last_ien']))
                {
                    $getmorepages = FALSE;    
                } else {
                    $getmorepages = ($pages <= $maxpages);    
                }
                $start_from_IEN = $bundle['last_ien'];
                if($bundle['matching_offset'] != NULL)
                {
                    $matching_offset = count($show_rows) + $bundle['matching_offset'];
                }
                $pending_orders_map = array_merge($pending_orders_map, $bundle['pending_orders_map']);
                $row_bundles[] = $formated_datarows;
//error_log("LOOK at page $pages getting more pages? ($getmorepages) >>>".print_r($row_bundles,TRUE));
            }
            $show_rows = $row_bundles[0];   //TODO FIX ARRAY MERGE!!!!! array_merge($row_bundles);
            $aResult = array('Pages'=>$pages
                            ,'Page'=>1
                            ,'RowsPerPage'=>$max_rows_one_call
                            ,'DataRows'=>$show_rows
                            ,'matching_offset' => $matching_offset
                            ,'pending_orders_map' => $pending_orders_map
                );
error_log("LOOK worklist maxrows=$max_rows_one_call result>>>".print_r($aResult,TRUE));
            return $aResult;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * Return array of valuse from the indicated action
     * This is good for developers to check results
     */
    function getPrivateValue($keynames)
    {
        try
        {
            if(!is_array($keynames))
            {
                $keynames_ar = array($keynames);
            } else {
                $keynames_ar = $keynames;
            }
            $result = array();
            foreach($keynames_ar as $keyname)
            {
                $varname = "m_{$keyname}";
                $result[$keyname] = $this->$varname;
            }
            return $result;
        } catch (\Exception $ex) {
            $msg = "Failed getting keynames because ".$ex;
            throw new \Exception($msg,99876,$ex);
        }
    }
    
    public function __toString()
    {
        try 
        {
            $infomsg = $this->getCustomInfoMessage();
            if($infomsg > '')
            {
                $infomsg_txt = "\n\tCustom info message=$infomsg";
            } else {
                $infomsg_txt = '';
            }
            $spid = $this->getSelectedPatientID();
            $is_authenticated = $this->isAuthenticated() ? 'YES' : 'NO';
            $displayname = $this->getSessionVariable('displayname');
            return 'EwdDao instance created at ' . $this->m_createdtimestamp
                    . ' isAuthenticated=[' . $is_authenticated . ']'
                    . ' selectedPatient=[' . $spid . ']'
                    . ' displayname=[' . $displayname . ']'
                    . $infomsg_txt;
        } catch (\Exception $ex) {
            return 'Cannot get toString of EwdDao because ' . $ex;
        }
    }

    public function getNotesDetailMap()
    {
        try
        {
            $myhelper = new \raptor_ewdvista\NotesHelper();
            $serviceName = $this->getCallingFunctionName();
            $pid = $this->getSelectedPatientID();
            if($pid == '')
            {
                throw new \Exception('Cannot get notes detail without a patient ID!');
            }

            //Get the notes data from EWD services
            $args = array();
            $args['patientId'] = $pid;
            $rawresult = $this->getServiceRelatedData($serviceName, $args);
            $notesdetail = $myhelper->getFormattedNotes($rawresult);
            return $notesdetail;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function setPatientID($sPatientID)
    {
        try
        {
            $this->setSessionVariable('selectedPatient',$sPatientID);
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getEHRUserID($fail_if_missing = TRUE)
    {
        try
        {
            $userduz = $this->getSessionVariable('userduz');
            if($userduz == NULL && $fail_if_missing)
            {
                throw new \Exception('No user is currently authenticated!');
            }
            return $userduz;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function cancelRadiologyOrder($patientid, $orderFileIen, $providerDUZ, $locationthing, $reasonCode, $cancelesig)
    {
        throw new \Exception("Not implemented $patientid, $orderFileIen, $providerDUZ, $locationthing, $reasonCode, $cancelesig");
    }

    public function createNewRadiologyOrder($orderChecks, $args)
    {
        throw new \Exception("Not implemented $orderChecks, $args");
    }

    public function createUnsignedRadiologyOrder($orderChecks, $args)
    {
        throw new \Exception("Not implemented $orderChecks, $args");
    }

    /**
     * TODO: Confirm with Joel and Rob that intermittent 'target' param issue is resolved.
     *       See email threads from 8/14 on the topic.
     */
    public function getAllHospitalLocationsMap()
    {
        try
        {
            $serviceName = 'getHospitalLocationsMap';   //Only gets 44 at a time
            $callservice = TRUE;
            $callcount=0;
            $maxcalls = 50;
            $prevend = ' ';
            $formatted = array();
            while($callservice)
            {
                $callcount++;
                $args = array();
                $args['target'] = $prevend;   //Start at the start
                $rawresult = $this->getServiceRelatedData($serviceName, $args);
                if(!isset($rawresult['value']))
                {
                    error_log("WARNING callcount=$callcount QUIT $serviceName ITERATIONS because NON-ARRAY RESULT prev=[$prevend] last=[$lastitem]"); 
                    $callservice = FALSE;
                } else {
                    $rawdatarows = $rawresult['value'];
                    $lastrawitem = end($rawdatarows);
                    $last_ar = explode('^',$lastrawitem);
                    $lastitem = $last_ar[1];
                    $moreformatted = array();
                    foreach($rawdatarows as $key=>$onerow)
                    {
                        $one_ar = explode('^',$onerow);
                        $newkey = $one_ar[0];
                        $moreformatted[$newkey] = $one_ar[1];
                    }
                    if(is_array($rawdatarows) && count($rawdatarows) > 0 && strcasecmp($prevend, $lastitem) < 0)
                    {
                        $prevend = $lastitem;
                        $callservice = TRUE;
                    } else {
                        $callservice = FALSE;
                    }
                    $formatted = $formatted + $moreformatted;
                }
                if($callcount >= $maxcalls)
                {
                    error_log("WARNING: TOO MANY ITERATIONS(hit $callcount with item $lastitem and max is $maxcalls) in getAllHospitalLocationsMap");
                    $formatted['GETMORE'] = "TOO MANY LOCATIONS";
                    $callservice = FALSE;
                }
            }
            return $formatted;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getAllergiesDetailMap()
    {
        /*
         * [10-Aug-2015 14:59:48 America/New_York] LOOK data format returned for 'getAllergiesDetail' is >>>Array
(
    [0] => Array
        (
            [DateReported] => 12/17/2007
            [Item] => CHOCOLATE
            [CausativeAgent] => DRUG, FOOD
            [SignsSymptoms] => Array
                (
                    [Snippet] => DIARRHEA
                    [Details] => DIARRHEA
                    [SnippetSameAsDetail] => 1
                )

            [DrugClasses] => Array
                (
                    [Snippet] => 
                    [Details] => 
                    [SnippetSameAsDetail] => 1
                )

            [Originator] =>  
            [ObservedHistorical] => Array
                (
                    [Snippet] => 
                    [Details] => 
                    [SnippetSameAsDetail] => 1
                )

        )

    [1] => Array
        (
            [DateReported] => 03/17/2005
            [Item] => PENICILLIN
            [CausativeAgent] => DRUG
            [SignsSymptoms] => Array
                (
                    [Snippet] => ITCHING,WATERING EYES
                    [Details] => ITCHING,WATERING EYES
                    [SnippetSameAsDetail] => 1
                )

            [DrugClasses] => Array
                (
                    [Snippet] => 
                    [Details] => 
                    [SnippetSameAsDetail] => 1
                )

            [Originator] =>  
            [ObservedHistorical] => Array
                (
                    [Snippet] => 
                    [Details] => 
                    [SnippetSameAsDetail] => 1
                )

        )

    [2] => Array
        (
            [DateReported] => 12/31/1969
            [Item] => ZOCOR
            [CausativeAgent] => DRUG
            [SignsSymptoms] => Array
                (
                    [Snippet] => HIVES
                    [Details] => HIVES
                    [SnippetSameAsDetail] => 1
                )

            [DrugClasses] => Array
                (
                    [Snippet] => 
                    [Details] => 
                    [SnippetSameAsDetail] => 1
                )

            [Originator] =>  
            [ObservedHistorical] => Array
                (
                    [Snippet] => 
                    [Details] => 
                    [SnippetSameAsDetail] => 1
                )

        )

)

         */
        $serviceName = $this->getCallingFunctionName();
        return $this->getServiceRelatedData($serviceName);
    }

    public function getChemHemLabs()
    {
        try
        {
            $serviceName = $this->getCallingFunctionName();
            $args = array();
            $args['patientId'] = $this->getSelectedPatientID();
            $args['fromDate'] = EwdUtils::getVistaDate(-1 * DEFAULT_GET_VISIT_DAYS);
            $args['toDate'] = EwdUtils::getVistaDate(0);
            
            //$rawresult = $this->getServiceRelatedData($serviceName, $args);
            $specimensArray = $this->getServiceRelatedData($serviceName, $args);;
            
            $labsResults = array();
            foreach ($specimensArray as $specimen){
                $specimen_rawTime = $specimen['timestamp'];
                $specimen_date = EwdUtils::convertVistaDateTimeToDate($specimen_rawTime);
                $specimen_time = EwdUtils::convertVistaDateTimeToDatetime($specimen_rawTime);//getVistaDateTimePart($specimen_rawTime, 'time');
                foreach($specimen['labResults'] as $labResult){
                    $labResult_value = $labResult['value'];
                    $labTest = $labResult['labTest'];
                    $labTest_name = $labTest['name'];
                    $labTest_units = $labTest['units'];
                    $labTest_refRange = $labTest['refRange'];
                    $labsResults[] = array(
                                            'name'      => $labTest_name,
                                            'date'      => $specimen_date,
                                            'datetime'  => $specimen_time,
                                            'value'     => $labResult_value,
                                            'units'     => $labTest_units,
                                            'refRange'  => $labTest_refRange,
                                            'rawTime'   => EwdUtils::convertVistaDateToYYYYMMDDtttt($specimen_rawTime)//$specimen_time//$specimen_rawTime //??? is that the place that should be fixed
                                            );
                    }
                }    
            return $labsResults;
        } catch (\Exception $ex) {
            throw $ex;
        }     
    }

    /**
     * If override_tracking_id is provided, then return dashboard for that order
     * instead of the currently selected order.
     */
    public function getDashboardDetailsMap($override_tracking_id = NULL)
    {
        try
        {
            $serviceName = $this->getCallingFunctionName();
            $oContext = \raptor\Context::getInstance();
            if ($override_tracking_id == NULL)
            {
                $tid = $oContext->getSelectedTrackingID();
            } else {
                $tid = trim($override_tracking_id);
            }
            if($tid == '')
            {
                throw new \Exception('Cannot get dashboard without a tracking ID!');
            }

            if ($oContext != NULL)
            {
                //Utilize the cache.
                $sThisResultName = "{$tid}" . REDAO_CACHE_NM_SUFFIX_DASHBOARD;
                $oRuntimeResultFlexCacheHandler = $oContext->getRuntimeResultFlexCacheHandler($this->m_groupname);
                if($oRuntimeResultFlexCacheHandler != NULL)
                {
                    $aCachedResult = $oRuntimeResultFlexCacheHandler->checkCache($sThisResultName);
                    if($aCachedResult !== NULL)
                    {
                        //Found it in the cache!
    //error_log("LOOK final bundle getDashboardDetailsMap PULLED FROM CACHE >>> ".print_r($aCachedResult, TRUE));  
                        return $aCachedResult;
                    }
                }
            } else {
                $oRuntimeResultFlexCacheHandler = NULL;
            }

            //Get the dashboard data from EWD services
            $namedparts = $this->getTrackingIDNamedParts($tid);
            $order_IEN = $namedparts['ien'];
            $onerow = NULL; //We MUST declare it here, else not set after the try block
            $therow = array();
            try
            {
                $onerow = $this->getWorklistDetailsMap(1,$order_IEN);
                if(!is_array($onerow) || !isset($onerow['DataRows']))
                {
                    throw new \Exception("Failed to get worklist row for $order_IEN >>>" . print_r($onerow,TRUE));
                }
            } catch (\Exception $ex) {
                throw new \Exception("Failed to get worklist row for $order_IEN because $ex",99876,$ex);
            }
            $datarows = $onerow['DataRows'];
            if(count($datarows) < 1)    //Do NOT check for exactly 1 because result returns ONE extra row sometimes! (Thats okay)
            {
                $rownum = 0;
                $errmsg = "Expected 1 data row for $order_IEN (got ".count($datarows).")";
                foreach($datarows as $onedatarow)
                {
                    $rownum++;
                    $errmsg .= "\n\tData Row #$rownum) ".print_r($onedatarow,TRUE);
                }
                throw new \Exception($errmsg);
            }
            foreach($datarows as $key=>$therow)
            {
                break;  //Only want to get the first row.
            }
            $args = array();
            $args['ien'] = $order_IEN;
            $result = $this->getServiceRelatedData($serviceName, $args);
            if(!is_array($result['radiologyOrder']))
            {
                throw new \Exception("Did not find array of radiologyOrder in ".print_r($result,TRUE));
            }
            if(!is_array($result['order']))
            {
                throw new \Exception("Did not find array of order in ".print_r($result,TRUE));
            }
            $radiologyOrder = $result['radiologyOrder'];
            $orderFileRec = $result['order'];
            $pid = $therow[\raptor\WorklistColumnMap::WLIDX_PATIENTID];
            $oPatientData = $this->getPatientMap($pid);
            if($oPatientData == NULL)
            {
                $msg = 'Did not get patient data of pid='.$pid
                        .' for trackingID=['.$tid.']';
                error_log($msg.">>>instance details=".print_r($this, TRUE));
                throw new \Exception($msg);
            }
            $dashboard = $this->m_dashboardHelper->getFormatted($tid, $pid, $radiologyOrder, $orderFileRec, $therow, $oPatientData);

            //Put it into the cache if we one
            if ($oRuntimeResultFlexCacheHandler != NULL)
            {
                try 
                {
    //error_log("LOOK getDashboardDetailsMap WENT INTO CACHE dashboard=".print_r($dashboard,TRUE));        
                    $oRuntimeResultFlexCacheHandler->addToCache($sThisResultName, $dashboard, CACHE_AGE_LABS);
                } catch (\Exception $ex) {
                    error_log("Failed to cache $sThisResultName result because " . $ex->getMessage());
                }
            }
            return $dashboard;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * A tracking ID can be an IEN or an SITE-IEN so
     * use this function instead of coding everywhere.
     */
    private function getTrackingIDNamedParts($tid)
    {
        $namedparts = array();
        $parts = explode('-',trim($tid));
        if(count($parts) == 1)
        {
            $namedparts['site'] = NULL; //Not specified in tid
            $namedparts['ien'] = trim($tid);
        } else {
            $namedparts['site'] = trim($parts[0]);
            $namedparts['ien'] = trim($parts[1]);
        }
        return $namedparts;
    }

    public function getDiagnosticLabsDetailMap()
    {
        /*
         * [10-Aug-2015 14:59:47 America/New_York] LOOK data format returned for 'getDiagnosticLabsDetail' is >>>Array
(
    [0] => Array
        (
            [DiagDate] => 03/16/2010 10:23 am
            [Creatinine] => 1.3 mg/dL
            [eGFR] => 56  mL/min/1.73 m^2
            [eGFR_Health] => warn
            [Ref] => (eGFR calculated) .9 - 1.4
        )

    [1] => Array
        (
            [DiagDate] => 03/16/2010 10:21 am
            [Creatinine] => 1.1 mg/dL
            [eGFR] => 68  mL/min/1.73 m^2
            [eGFR_Health] => good
            [Ref] => (eGFR calculated) .9 - 1.4
        )

    [2] => Array
        (
            [DiagDate] => 03/16/2010 10:20 am
            [Creatinine] => 1.3 mg/dL
            [eGFR] => 56  mL/min/1.73 m^2
            [eGFR_Health] => warn
            [Ref] => (eGFR calculated) .9 - 1.4
        )

    [3] => Array
        (
            [DiagDate] => 03/16/2010 10:18 am
            [Creatinine] => <span class='medical-value-danger'>!! 1.5 mg/dL !!</span>
            [eGFR] => 48  mL/min/1.73 m^2
            [eGFR_Health] => warn
            [Ref] => (eGFR calculated) .9 - 1.4
        )

    [4] => Array
        (
            [DiagDate] => 03/16/2010 10:17 am
            [Creatinine] => 1.2 mg/dL
            [eGFR] => 62  mL/min/1.73 m^2
            [eGFR_Health] => good
            [Ref] => (eGFR calculated) .9 - 1.4
        )

)

         */
        $serviceName = $this->getCallingFunctionName();
        return $this->getServiceRelatedData($serviceName);
    }

    public function getEGFRDetailMap()
    {
        /*
         * [10-Aug-2015 14:59:47 America/New_York] LOOK data format returned for 'getEGFRDetail' is >>>Array
(
    [LATEST_EGFR] => 56
    [MIN_EGFR_10DAYS] => 
    [MIN_EGFR_15DAYS] => 
    [MIN_EGFR_30DAYS] => 
    [MIN_EGFR_45DAYS] => 
    [MIN_EGFR_60DAYS] => 
    [MIN_EGFR_90DAYS] => 
)

         */
        $serviceName = $this->getCallingFunctionName();
        return $this->getServiceRelatedData($serviceName);
    }

    public function getEncounterStringFromVisit($vistitTo)
    {
        $serviceName = $this->getCallingFunctionName();
        return $this->getServiceRelatedData($serviceName);
    }

    public function getHospitalLocationsMap($startingitem)
    {
        $serviceName = $this->getCallingFunctionName();
        return $this->getServiceRelatedData($serviceName);
    }

    public function getImagingTypesMap()
    {
        //Returns results like this...
        //$result['37'] = 'ANGIO/NEURO/INTERVENTIONAL';
        //$result['5'] = 'MRI';
        try
        {
            $serviceName = $this->getCallingFunctionName();
            $rawresult = $this->getServiceRelatedData($serviceName);
            $rawdata = $rawresult['value'];
            $formatted = array();
            foreach($rawdata as $key=>$onerow)
            {
                $one_ar = explode('^',$onerow);
                $newkey = $one_ar[3];
                $formatted[$newkey] = $one_ar[1];
            }
            return $formatted;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getMedicationsDetailMap($atriskmeds = NULL)
    {
        try
        {
            $myhelper = new \raptor_ewdvista\MedicationHelper();
            $serviceName = $this->getCallingFunctionName();
            $pid = $this->getSelectedPatientID();
            if($pid == '')
            {
                throw new \Exception('Cannot get medication detail without a patient ID!');
            }

            //Get the notes data from EWD services
            $args = array();
            $args['patientId'] = $pid;
            $rawresult = $this->getServiceRelatedData($serviceName, $args);
            $notesdetail = $myhelper->getFormattedMedicationsDetail($rawresult, $atriskmeds);
            return $notesdetail;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getOrderOverviewMap()
    {
        /*
         * [10-Aug-2015 14:59:47 America/New_York] LOOK data format returned for 'getOrderOverview' is >>>Array
(
    [RqstBy] => ZZLABTECH,FORTYEIGHT
    [PCP] => Unknown
    [AtP] => Unknown
    [RqstStdy] => CT ABDOMEN W/O CONT
    [RsnStdy] => TEST
)

         */
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function getOrderableItems($imagingTypeId)
    {
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function getPathologyReportsDetailMap()
    {
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function getPatientIDFromTrackingID($sTrackingID)
    {
        $serviceName = $this->getCallingFunctionName();
error_log("Look about to call service $serviceName($sTrackingID) ...");        
        
        $tid = trim($sTrackingID);
        if($tid == '')
        {
            throw new \Exception("Cannot get patient ID without a tracking ID!");
        }
        $namedparts = $this->getTrackingIDNamedParts($tid);
        $args['ien'] = $namedparts['ien'];
        $result = $this->getServiceRelatedData($serviceName, $args);
error_log("LOOK EWD DAO $serviceName($sTrackingID) result = ".print_r($result,TRUE));
        if(!isset($result['result']))
        {
            throw new \Exception("Missing patient ID result from tracking ID value $sTrackingID: ".print_r($result,TRUE));
        }
        $patientID = $result['result'];
        return $patientID;
    }

    public function getPendingOrdersMap()
    {
        /*
         * [10-Aug-2015 14:59:48 America/New_York] LOOK data format returned for 'getPendingOrdersMap' is >>>Array
(
    [2005] => Array
        (
            [0] => 2005
            [1] => CT
            [2] => CT ABDOMEN W/O CONT
        )

    [2006] => Array
        (
            [0] => 2006
            [1] => CT
            [2] => CT ABDOMEN W/O CONT
        )

    [2009] => Array
        (
            [0] => 2009
            [1] => CT
            [2] => CT ABDOMEN W/O CONT
        )

)

         */
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function getProblemsListDetailMap()
    {
        /*
         * [10-Aug-2015 14:59:47 America/New_York] LOOK data format returned for 'getProblemsListDetail' is >>>Array
(
    [0] => Array
        (
            [Title] => Meningitis, Listeria
            [OnsetDate] => 06/07/2010 12:00 am
            [Snippet] => Meningitis, Listeria
            [Details] => Array
                (
                    [Type of Note] => Problem
                    [Provider Narrative] => Meningitis, Listeria
                    [Note Narrative] =>  
                    [Status] => A
                    [Observer] => PROVIDER,THIRTYTWO
                    [Comment] =>  
                    [Facility] => CAMP MASTER
                )

        )

    [1] => Array
        (
            [Title] => Hypertension
            [OnsetDate] => 04/07/2005 12:00 am
            [Snippet] => Hypertension
            [Details] => Array
                (
                    [Type of Note] => Problem
                    [Provider Narrative] => Hypertension
                    [Note Narrative] =>  
                    [Status] => A
                    [Observer] => ZZVEHU,ONEHUNDRED
                    [Comment] =>  
                    [Facility] => CAMP MASTER
                )

        )

    [2] => Array
        (
            [Title] => Hyperlipidemia
            [OnsetDate] => 04/07/2005 12:00 am
            [Snippet] => Hyperlipidemia
            [Details] => Array
                (
                    [Type of Note] => Problem
                    [Provider Narrative] => Hyperlipidemia
                    [Note Narrative] =>  
                    [Status] => A
                    [Observer] => ZZVEHU,ONEHUNDRED
                    [Comment] =>  
                    [Facility] => CAMP MASTER
                )

        )

    [3] => Array
        (
            [Title] => Acute myocardial infarction, unspecified...
            [OnsetDate] => 03/17/2005 12:00 am
            [Snippet] => Acute myocardial infarction, unspecified...
            [Details] => Array
                (
                    [Type of Note] => Problem
                    [Provider Narrative] => Acute myocardial infarction, unspecified site, episode of care unspecified
                    [Note Narrative] =>  
                    [Status] => A
                    [Observer] => DOCTOR,ONE
                    [Comment] =>  
                    [Facility] => CAMP MASTER
                )

        )

    [4] => Array
        (
            [Title] => Chronic Systolic Heart failure
            [OnsetDate] => 03/09/2004 12:00 am
            [Snippet] => Chronic Systolic Heart failure
            [Details] => Array
                (
                    [Type of Note] => Problem
                    [Provider Narrative] => Chronic Systolic Heart failure
                    [Note Narrative] =>  
                    [Status] => A
                    [Observer] => ZZLABTECH,SPECIAL
                    [Comment] =>  
                    [Facility] => CAMP MASTER
                )

        )

    [5] => Array
        (
            [Title] => Diabetes Mellitus Type II or unspecified
            [OnsetDate] => 02/08/2000 12:00 am
            [Snippet] => Diabetes Mellitus Type II or unspecified
            [Details] => Array
                (
                    [Type of Note] => Problem
                    [Provider Narrative] => Diabetes Mellitus Type II or unspecified
                    [Note Narrative] =>  
                    [Status] => A
                    [Observer] => DOCTOR,ONE
                    [Comment] =>  
                    [Facility] => CAMP MASTER
                )

        )

)

         */
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function getProcedureLabsDetailMap()
    {
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function getProviders($neworderprovider_name)
    {
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function getRadiologyCancellationReasons()
    {
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function getRadiologyOrderChecks($args)
    {
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function getRadiologyOrderDialog($imagingTypeId, $patientId)
    {
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function getRadiologyReportsDetailMap()
    {
        /*
         * [10-Aug-2015 14:59:53 America/New_York] LOOK data format returned for 'getRadiologyReportsDetail' is >>>Array
(
    [0] => Array
        (
            [Title] => CT ABDOMEN W/O CONT
            [ReportedDate] => 07/17/2012 10:22 am
            [Snippet] => CT ABDOMEN W/O CONT...
            [Details] => Array
                (
                    [Procedure Name] => CT ABDOMEN W/O CONT
                    [Report Status] => No Report
                    [CPT Code] =>  
                    [Reason For Study] =>  
                    [Clinical HX] => 
                    [Impression] =>  
                    [Report] => CT ABDOMEN W/O CONT<br />
   <br />
Exm Date: JUL 17, 2012@10:22<br />
Req Phys: ZZLABTECH,FORTYEIGHT           Pat Loc: CARDIOLOGY (Req'g Loc)<br />
                                         Img Loc: CT SCAN<br />
                                         Service: Unknown<br />
<br />
 <br />
<br />
(Case 48 WAITING )   CT ABDOMEN W/O CONT              (CT   Detailed) CPT:<br />
     Reason for Study: TEST<br />
<br />
    Clinical History:<br />
<br />
    Report Status: No Report<br />
   <br />

                    [Facility] =>  
                )

            [AccessionNumber] => 071712-48
            [CaseNumber] => 48
            [ReportID] => 6879282.8977-1
        )

    [1] => Array
        (
            [Title] => CT ABDOMEN W/O CONT
            [ReportedDate] => 07/17/2012 09:01 am
            [Snippet] => CT ABDOMEN W/O CONT...
            [Details] => Array
                (
                    [Procedure Name] => CT ABDOMEN W/O CONT
                    [Report Status] => No Report
                    [CPT Code] =>  
                    [Reason For Study] =>  
                    [Clinical HX] => 
                    [Impression] =>  
                    [Report] => CT ABDOMEN W/O CONT<br />
   <br />
Exm Date: JUL 17, 2012@09:01<br />
Req Phys: ZZLABTECH,FORTYEIGHT           Pat Loc: CARDIOLOGY (Req'g Loc)<br />
                                         Img Loc: CT SCAN<br />
                                         Service: Unknown<br />
<br />
 <br />
<br />
(Case 44 WAITING )   CT ABDOMEN W/O CONT              (CT   Detailed) CPT:<br />
     Reason for Study: TESTING<br />
<br />
    Clinical History:<br />
<br />
    Report Status: No Report<br />
   <br />

                    [Facility] =>  
                )

            [AccessionNumber] => 071712-44
            [CaseNumber] => 44
            [ReportID] => 6879282.9098-1
        )

         */
        
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function getRawVitalSignsMap()
    {
        try
        {
            $pid = $this->getSelectedPatientID();
            if($pid == NULL)
            {
                throw new \Exception('Cannot return vitals when there is no selected patient!');
            }
            $oContext = \raptor\Context::getInstance();
            if ($oContext != NULL)
            {
                //Utilize the cache.
                $sThisResultName = "{$pid}" . REDAO_CACHE_NM_SUFFIX_VITALS;
                $oRuntimeResultFlexCacheHandler = $oContext->getRuntimeResultFlexCacheHandler($this->m_groupname);
                if ($oRuntimeResultFlexCacheHandler != NULL)
                {
                    $aCachedResult = $oRuntimeResultFlexCacheHandler->checkCache($sThisResultName);
                    if ($aCachedResult !== NULL)
                    {
                        //Found it in the cache!
//error_log("LOOK final bundle getRawVitalSignsMap PULLED FROM CACHE >>> ".print_r($aCachedResult, TRUE));  
                        return $aCachedResult;
                    }
                }
            } else {
                $oRuntimeResultFlexCacheHandler = NULL;
            }
            
            $myhelper = new \raptor_ewdvista\VitalsHelper();
            $serviceName = $this->getCallingFunctionName();
            $args = array();
            $args['patientId'] = $pid;
            $rawresult = array();
            $rawresult['result'] = $this->getServiceRelatedData($serviceName, $args);
            $bundle = $myhelper->getFormattedSuperset($rawresult);
            
//error_log("LOOK final bundle getRawVitalSignsMap ".print_r($bundle, TRUE));  
            if ($oRuntimeResultFlexCacheHandler != NULL)
            {
                try 
                {
//error_log("LOOK final bundle getRawVitalSignsMap WENT INTO CACHE!!!");  
                    $oRuntimeResultFlexCacheHandler->addToCache($sThisResultName, $bundle, CACHE_AGE_LABS);
                } catch (\Exception $ex) {
                    error_log("Failed to cache $sThisResultName result because " . $ex->getMessage());
                }
            }
            return $bundle;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getSurgeryReportsDetailMap()
    {
        /*
         * [10-Aug-2015 14:59:47 America/New_York] LOOK data format returned for 'getSurgeryReportsDetail' is >>>Array
(
    [0] => Array
        (
            [Title] => LEFT INGUINAL HERNIA REPAIR WITH MESH
            [ReportDate] => 12/31/1969 07:00 pm
            [Snippet] => LOCAL TITLE: OPERATION REPORT           ...
            [Details] => LOCAL TITLE: OPERATION REPORT                                   <br />
DATE OF NOTE: DEC 08, 2006@07:30     ENTRY DATE: DEC 08, 2006@14:01:19      <br />
     SURGEON: PROVIDER,ONE            ATTENDING: TDPROVIDER,ONE               <br />
     URGENCY:                            STATUS: COMPLETED                     <br />
     SUBJECT: Case #: 10007                                                    <br />
<br />
SURGEON:                PROVIDER,ONE <br />
 <br />
 1ST ASST:               PROVIDER,TWO <br />
 <br />
 ATTENDING:              TDPROVIDER,ONE <br />
 <br />
 PROCEDURE:            LEFT INGUINAL HERNIA REPAIR WITH MESH<br />
 <br />
 HISTORY:  Essentially patient  underwent  preop evaluation  for  left <br />
 inguinal mass  noted  since  September 2004.    Recently  PT became  more <br />
 symptomatic with  increased size and  tenderness.  Patient  denied any history<br />
 of melena or bloody stools.   Denied a history of constipation or diarrhea. <br />
 No recent fevers or chills.   He was admitted on December 7, 2006,<br />
 for an elective left inguinal hernia repair. <br />
 <br />
 SUMMARY OF PROCEDURES:   After  consent was  obtained,  the  patient was <br />
 prepped and  draped in  sterile fashion.   Lidocaine  1% was  used to <br />
 anesthetize the  skin and a 5-cm incision was made in the left groin.  <br />
 The skin and subcu was dissected down to the  external oblique fascia.  <br />
 The fascia was incised to the external ring and the spermatic cord and<br />
 all its contents were isolated  with a Penrose drain.  The  hernia sac<br />
 was then dissected and reduced into the large direct inguinal defect.  <br />
 Three large  mesh plugs  were secured  together and  used to  plug the<br />
 direct  defect  and  secured  in place  with  2  interrupted  Prolene<br />
 stitches.   An onlay patch was  then applied and secured  to the pubic<br />
 tubercle  and secured to the fascial edges using a running 2-0 Prolene<br />
 suture on  either side.  The external oblique was then closed over the<br />
 repair, being cognizant  of the ilioinguinal  nerve.  All superficial<br />
 bleeding was  controlled with electrocautery.   Copious irrigation was<br />
 used  and additional 1% Lidocaine  was used to  anesthetize the subcu and<br />
 fascia.  Scarpa  fascia was closed  using 4-0 Vicryl.   Additional 4-0  <br />
 Vicryl was  used in  a subcuticular  fashion  to close  the skin.  <br />
 Steri-Strips were applied and dressings.   The patient  was extubated and<br />
 stable to recovery, tolerated the procedure well.   The attending<br />
 physician, TDPROVDIER,ONE, was scrubbed during the entire case.<br />
 <br />
/es/ e9@sWkjz\(hy<br />
Mg<br />
Signed: 12/08/2006 18:19<br />
 <br />
/es/ e9@sf?BKFw\srt<br />
Mg<br />
Cosigned: 12/11/2006 08:45<br />
=========================================================================<br />
 LOCAL TITLE: NURSE INTRAOPERATIVE REPORT                        <br />
DATE OF NOTE: DEC 08, 2006@07:30     ENTRY DATE: DEC 08, 2006@10:36:08      <br />
      AUTHOR: ZZTDNURSE,ONE        EXP COSIGNER:                           <br />
     URGENCY:                            STATUS: COMPLETED                     <br />
     SUBJECT: Case #: 10007                                                    <br />
<br />
Operating Room:  OR4                    Surgical Priority: ELECTIVE<br />
<br />
Patient in Hold: DEC 08, 2006  07:00    Patient in OR:  DEC 08, 2006  07:30<br />
Operation Begin: DEC 08, 2006  08:00    Operation End:  DEC 08, 2006  09:45<br />
                                        Patient Out OR: DEC 08, 2006  10:00<br />
<br />
Major Operations Performed:<br />
Primary: LEFT INGUINAL HERNIA REPAIR<br />
<br />
Wound Classification: CLEAN<br />
Operation Disposition: PACU (RECOVERY ROOM)<br />
Discharged Via: STRETCHER<br />
<br />
Surgeon: PROVIDER,ONE                   First Assist: PROVIDER,TWO<br />
Attend Surg: TDPROVIDER,ONE             Second Assist: N/A<br />
Anesthetist: PROVIDER,THREE             Assistant Anesth: N/A<br />
<br />
Other Scrubbed Assistants: N/A<br />
<br />
OR Support Personnel:<br />
  Scrubbed                              Circulating<br />
  NURSE,ONE ()                          TDNURSE,ONE ()<br />
<br />
Other Persons in OR: N/A<br />
<br />
Preop Mood:       RELAXED               Preop Consc:    ALERT-ORIENTED<br />
Preop Skin Integ: INTACT                Preop Converse: N/A<br />
<br />
Valid Consent/ID Band Confirmed By: TDNURSE,ONE<br />
Mark on Surgical Site Confirmed: YES<br />
  Marked Site Comments: NO COMMENTS ENTERED<br />
<br />
Preoperative Imaging Confirmed:  YES<br />
  Imaging Confirmed Comments: NO COMMENTS ENTERED<br />
<br />
Time Out Verification Completed: YES<br />
  Time Out Verified Comments: NO COMMENTS ENTERED<br />
<br />
Skin Prep By: PROVIDER,TWO              Skin Prep Agent: BETADINE<br />
Skin Prep By (2): N/A                   2nd Skin Prep Agent: N/A<br />
<br />
Preop Surgical Site Hair Removal by: PROVIDER,ONE<br />
Surgical Site Hair Removal Method: DEPILATORY<br />
  Hair Removal Comments: NO COMMENTS ENTERED<br />
<br />
Surgery Position(s): <br />
  SUPINE                                Placed: N/A<br />
<br />
Restraints and Position Aids: <br />
  SAFETY STRAP                      Applied By: N/A<br />
  ARMBOARD                          Applied By: N/A<br />
<br />
Electrocautery Unit:       #4<br />
ESU Coagulation Range:     30<br />
ESU Cutting Range:         N/A<br />
Electroground Position(s): RIGHT ANT THIGH<br />
<br />
Material Sent to Laboratory for Analysis: <br />
Specimens: <br />
  Left Inguinal Hernia Sac<br />
Cultures:  N/A<br />
<br />
Anesthesia Technique(s):<br />
  MONITORED ANESTHESIA CARE  (PRINCIPAL)<br />
<br />
Tubes and Drains: N/A<br />
<br />
Tourniquet: N/A<br />
<br />
Thermal Unit: N/A<br />
<br />
Prosthesis Installed: N/A<br />
<br />
Medications: <br />
  BUPIVACAINE 0.5% 50ML INJ<br />
    Time Administered: DEC 08, 2006  07:45<br />
      Route: INFILTRATE                 Dosage: 15cc<br />
      Ordered By: PROVIDER,ONE          Admin By: PROVIDER,ONE<br />
      Comments: Used 1:1 with LIDOCAINE<br />
  LIDOCAINE 1% 50ML MDV<br />
    Time Administered: DEC 08, 2006  07:45<br />
      Route: INFILTRATE                 Dosage: 15cc<br />
      Ordered By: PROVIDER,ONE          Admin By: PROVIDER,ONE<br />
      Comments: Used 1:1 with BUPIVACAINE<br />
<br />
Irrigation Solution(s): <br />
  NORMAL SALINE<br />
<br />
Blood Replacement Fluids: N/A<br />
<br />
Sponge Count Correct:     YES<br />
Sharps Count Correct:     YES<br />
Instrument Count Correct: YES<br />
Counter:                  NURSE,ONE<br />
Counts Verified By:       TDNURSE,ONE<br />
<br />
Dressing: 4X4<br />
Packing:  N/A<br />
<br />
Blood Loss: 9 ml                        Urine Output: <br />
<br />
Postoperative Mood:           RELAXED<br />
Postoperative Consciousness:  ALERT-ORIENTED<br />
Postoperative Skin Integrity: INTACT<br />
Postoperative Skin Color:     N/A<br />
<br />
Laser Unit(s): N/A<br />
<br />
Sequential Compression Device: N/A<br />
<br />
Cell Saver(s): N/A<br />
<br />
Devices: N/A<br />
<br />
Nursing Care Comments: NO COMMENTS ENTERED<br />
 <br />
/es/ hbi&zHn)pf7<br />
gb<br />
Signed: 12/08/2006 17:49<br />
=========================================================================<br />
 LOCAL TITLE: ANESTHESIA REPORT                                  <br />
DATE OF NOTE: DEC 08, 2006@07:30     ENTRY DATE: DEC 08, 2006@11:00:04      <br />
      AUTHOR: PROVIDER,THREE          ATTENDING: TDPROVIDER,TWO               <br />
     URGENCY:                            STATUS: COMPLETED                     <br />
     SUBJECT: Case #: 10007                                                    <br />
<br />
Operating Room: OR4<br />
<br />
Anesthetist: PROVIDER,THREE             Relief Anesth: <br />
Anesthesiologist: TDPROVIDER,TWO        Assist Anesth: <br />
Attending Code: 4. STAFF ASSISTING RESIDENT<br />
<br />
Anes Begin:  DEC 08, 2006  07:00        Anes End:  DEC 08, 2006  10:00<br />
<br />
ASA Class: 1-NO DISTURB.<br />
<br />
Operation Disposition: PACU (RECOVERY ROOM)<br />
<br />
Anesthesia Technique(s): <br />
MONITORED ANESTHESIA CARE  (PRINCIPAL)<br />
  Agent:     PROPOFOL 10MG/ML INJ,EMULSION<br />
  Intubated: NO<br />
<br />
Procedure(s) Performed:<br />
Principal: LEFT INGUINAL HERNIA REPAIR<br />
<br />
Medications:<br />
  BUPIVACAINE 0.5% 50ML INJ<br />
    Time Administered: DEC 08, 2006  07:45<br />
      Route: INFILTRATE                 Dosage: 15cc<br />
      Ordered By: PROVIDER,ONE          Admin By: PROVIDER,ONE<br />
      Comments: Used 1:1 with LIDOCAINE<br />
  LIDOCAINE 1% 50ML MDV<br />
    Time Administered: DEC 08, 2006  07:45<br />
      Route: INFILTRATE                 Dosage: 15cc<br />
      Ordered By: PROVIDER,ONE          Admin By: PROVIDER,ONE<br />
      Comments: Used 1:1 with BUPIVACAINE<br />
<br />
Intraoperative Blood Loss: 9 ml         Urine Output: <br />
PAC(U) Admit Score:                     PAC(U) Discharge Score: <br />
<br />
Postop Anesthesia Note Date/Time: <br />
 <br />
/es/ z5X`0I&Dq*MK]8<br />
`5(D|v#OX<br />
Signed: 12/08/2006 18:29<br />
=========================================================================<br />
        )

    [1] => Array
        (
            [Title] => RIH
            [ReportDate] => 12/31/1969 07:00 pm
            [Snippet] => No reports are available for this case.<...
            [Details] => No reports are available for this case.<br />
        )

)

         */
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function getUserSecurityKeys()
    {
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function getVisits()
    {
        try
        {
            $serviceName = $this->getCallingFunctionName();
            $args = array();
            $args['patientId'] = $this->getSelectedPatientID();
            $args['fromDate'] = EwdUtils::getVistaDate(-1 * DEFAULT_GET_VISIT_DAYS);
            $args['toDate'] = EwdUtils::getVistaDate(0);

            $rawresult = $this->getServiceRelatedData($serviceName, $args);
            $visitAry = $rawresult['value'];

            foreach ($visitAry as $visit) {
                $a = explode('^', $visit);
                $l = explode(';', $a[0]); //first field is an array "location name;visit timestamp;locationID"
                $aryItem = array(
                    //'raw' => $visit,
                    'locationName' => $l[0],
                    'locationId' => $l[2],
                    'visitTimestamp' => EwdUtils::convertVistaDateToYYYYMMDD($a[1]), //same as $l[1]
                    'visitTO' => $a[2]
                );
                $result[] = $aryItem;   //Already acending
            }
            $aSorted = array_reverse($result); //Now this is descrnding.
            return $aSorted;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getVistaAccountKeyProblems()
    {
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function getVitalsDetailMap()
    {
        $vitalsbundle = $this->getRawVitalSignsMap();
        if(isset($vitalsbundle[0]))
        {
            //error_log("LOOK getVitalsDetailMap >>> ".print_r($vitalsbundle[0],TRUE));
            return $vitalsbundle[0];
        }
        //Return an empty array.
        return array(); 
    }

    public function getVitalsDetailOnlyLatestMap()
    {
        $vitalsbundle = $this->getRawVitalSignsMap();
        if(isset($vitalsbundle[2]))
        {
            //error_log("LOOK getVitalsDetailOnlyLatestMap >>> ".print_r($vitalsbundle[2],TRUE));
            return $vitalsbundle[2];
        }
        //Return an empty array.
        return array(); 
    }

    public function getVitalsSummaryMap()
    {
        /*
         * [10-Aug-2015 14:59:47 America/New_York] LOOK data format returned for 'getVitalsSummary' is >>>Array
(
    [Temperature] => Array
        (
            [Date of Measurement] => 08/17/2010 04:03 pm
            [Measurement Value] => 99.5 F
        )

    [Heart Rate] => Array
        (
            [Date of Measurement] => 
            [Measurement Value] => None Found
        )

    [Blood Pressure] => Array
        (
            [Date of Measurement] => 08/17/2010 04:03 pm
            [Measurement Value] => 190/85 mmHg
        )

    [Height] => Array
        (
            [Date of Measurement] => 06/10/2010 08:11 am
            [Measurement Value] => 71 in (180.3 cms)
        )

    [Weight] => Array
        (
            [Date of Measurement] => 06/10/2010 08:11 am
            [Measurement Value] => 175 lb (79.4 kgs)
        )

    [Body Mass Index] => Array
        (
            [Date of Measurement] => 06/10/2010 08:11 am
            [Measurement Value] => 24 
        )

)

         */
        try
        {
            $vitalsbundle = $this->getRawVitalSignsMap();
            $myhelper = new \raptor_ewdvista\VitalsHelper();
            $summary = $myhelper->getVitalsSummary($vitalsbundle);
error_log("LOOK final VitalsSummary ".print_r($summary, TRUE));  
            return $summary;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function isProvider()
    {
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function signNote($newNoteIen, $eSig)
    {
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function userHasKeyOREMAS()
    {
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function validateEsig($eSig)
    {
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function verifyNoteTitleMapping($checkVistaNoteIEN, $checkVistaNoteTitle)
    {
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function writeRaptorGeneralNote($noteTextArray, $encounterString, $cosignerDUZ)
    {
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function writeRaptorSafetyChecklist($aChecklistData, $encounterString, $cosignerDUZ)
    {
        $serviceName = $this->getCallingFunctionName();
	return $this->getServiceRelatedData($serviceName);
    }

    public function invalidateCacheForEverything()
    {
        try
        {
            $oContext = \raptor\Context::getInstance();
            $oRuntimeResultFlexCacheHandler = $oContext->getRuntimeResultFlexCacheHandler($this->m_groupname);
            if ($oRuntimeResultFlexCacheHandler != NULL)
            {
                $oRuntimeResultFlexCacheHandler->invalidateRaptorCacheAllDataAndFlags();
            }
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    public function invalidateCacheForOrder($tid)
    {
        try
        {
            $oContext = \raptor\Context::getInstance();
            $oRuntimeResultFlexCacheHandler = $oContext->getRuntimeResultFlexCacheHandler($this->m_groupname);
            if ($oRuntimeResultFlexCacheHandler != NULL)
            {
                $oRuntimeResultFlexCacheHandler->invalidateRaptorCacheData("{$tid}" . REDAO_CACHE_NM_SUFFIX_DASHBOARD);
                $oRuntimeResultFlexCacheHandler->invalidateRaptorCacheData(REDAO_CACHE_NM_WORKLIST);
            }
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function invalidateCacheForPatient($pid)
    {
        try
        {
            $sThisResultName = "{$pid}" . REDAO_CACHE_NM_SUFFIX_VITALS;
            $oContext = \raptor\Context::getInstance();
            $oRuntimeResultFlexCacheHandler = $oContext->getRuntimeResultFlexCacheHandler($this->m_groupname);
            if ($oRuntimeResultFlexCacheHandler != NULL)
            {
                $oRuntimeResultFlexCacheHandler->invalidateRaptorCacheData($sThisResultName);
                $oRuntimeResultFlexCacheHandler->invalidateRaptorCacheData(REDAO_CACHE_NM_WORKLIST);
            }
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getSelectedPatientID()
    {
        //return $this->m_selectedPatient;
        return $this->getSessionVariable('selectedPatient');
    }

    public function getPatientMap($sPatientID)
    {
        $serviceName = $this->getCallingFunctionName();
        $args = array();
        $args['patientId'] = $sPatientID;
        $rawresult = $this->getServiceRelatedData($serviceName, $args);
        $a = explode('^', $rawresult['value']);
        $result = array();
        
        if(isset($a[2]) && $a[2] > '')
        {
            $vista_dob = trim($a[2]);
            $dob = \raptor_ewdvista\EwdUtils::convertVistaDateTimeToDate($vista_dob);
        } else {
            $dob = '';
        }
        
 	$result['patientName']  			= $a[0];
        $result['ssn']          			= $a[3];
        $result['gender']       			= $a[1];
        $result['dob']          			= $dob;
        $result['ethnicity']    			= "todo";
        $result['age']          			= $a[14];
        $result['maritalStatus']			= "todo";
        $result['age']          			= "todo";
        $result['mpiPid']       			= "todo";
        $result['mpiChecksum']  			= "todo";
        $result['localPid']     			= "todo";
        $result['sitePids']     			= "todo";
        $result['vendorPid']    			= "todo";
        $result['location'] 				= "Room:todo / Bed:todo ";
        $result['cwad'] 				= "todo";
        $result['restricted'] 				= "todo";
        $result['admitTimestamp'] 			= date("m/d/Y h:i a", strtotime("01/01/1950 01:01 a"));
        $result['serviceConnected']                     = "todo";
        $result['scPercent'] 				= "todo";
        $result['inpatient'] 				= "todo";
        $result['deceasedDate'] 			= "todo";
        $result['confidentiality'] 			= "todo";
        $result['needsMeansTest'] 			= "todo";
        $result['patientFlags'] 			= "todo";
        $result['cmorSiteId']	 			= "todo";
        $result['activeInsurance'] 			= "todo";
        $result['isTestPatient'] 			= "todo";
        $result['currentMeansStatus']                   = "todo";
        $result['hasInsurance'] 			= "todo";
        $result['preferredFacility']                    = "todo";
        $result['patientType'] 				= "todo";
        $result['isVeteran'] 				= "todo";
        $result['isLocallyAssignedMpiPid']              = "todo";
        $result['sites'] 				= "todo";
        $result['teamID'] 				= "todo";
        $result['teamName'] 				= "todo-Unknown";
        $result['teamPcpName'] 				= "todo-Unknown";
        $result['teamAttendingName']                    = "todo-Unknown";
        $result['mpiPid'] 				= "todo-Unknown";
        $result['mpiChecksum'] 				= "todo-Unknown";
      
        //TODO --- format the raw content
	return $result;
    }

}
