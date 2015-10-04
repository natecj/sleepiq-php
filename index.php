#!/opt/local/bin/php55
<?php

require_once 'SleepIQ.php';
require_once 'MinDB.php';

$sleepiq_user = "natecj@gmail.com";
$sleepiq_pass = "QqiQU9LzCr";

$database_host = "127.0.0.1";
$database_user = "root";
$database_pass = "aobdev";
$database_name = "sleepiq";


$start_date = '2014-07-10';
$end_date   = '2014-08-10';
$period = new DatePeriod(
  new DateTime($start_date),
  new DateInterval('P1D'),
  new DateTime($end_date)
);
$dates = [];
foreach( $period as $day ) 
  $dates[] = $day->format('Y-m-d');

  
try
{
  $MinDB   = new MinDB( $database_host, $database_user, $database_pass, $database_name );
  $SleepIQ = new SleepIQ\API( $sleepiq_user, $sleepiq_pass );
  
  // Iterate over beds
  foreach( $SleepIQ->beds() as $bed ) {
  
    // Insert Bed
    if ( !$MinDB->exists( "beds", "bedId", $bed->bedId ) )
      $MinDB->insert( "beds", $bed->getDatabaseData() );
    else
      $MinDB->update( "beds", $bed->getDatabaseData(), "bedId", $bed->bedId );
  
    // Iterate over sleepers
    foreach( $bed->sleepers as $sleeper ) {
    
      // Insert Sleeper
      if ( !$MinDB->exists( "sleepers", "sleeperId", $sleeper->sleeperId ) )
        $MinDB->insert( "sleepers", $sleeper->getDatabaseData() );
      else
        $MinDB->update( "sleepers", $sleeper->getDatabaseData(), "sleeperId", $sleeper->sleeperId );
        
      // Iterate over dates
      foreach( $dates as $date ) {
        sleep(3);
        $sleep_data = $sleeper->getSleepData( $date, "D1" );
        
        // Calculate averages that are missing
        $total_sessions   = 0;
        $avgSleepNumber   = 0;
        $avgSleepQuotient = 0;
        foreach( $sleep_data->sleepData as $temp ) {
          foreach( $temp->sessions as $session ) {
            $total_sessions++;
            $avgSleepNumber   += $session->sleepNumber;
            $avgSleepQuotient += $session->sleepQuotient;
          }
        }
        if ( $total_sessions > 0 ) {
          $avgSleepNumber   = $avgSleepNumber   / $total_sessions;
          $avgSleepQuotient = $avgSleepQuotient / $total_sessions;
        } else {
          $avgSleepNumber   = 0;
          $avgSleepQuotient = 0;
        }
      
        // Insert Sleep Data
        $db_sleep_data_id = $MinDB->queryFetchVar( "SELECT id FROM sleep_datas WHERE sleeperId = '".$MinDB->escapeString( $sleeper->sleeperId )."' AND date = '".$MinDB->escapeString( $date )."'", "id" );
        $db_sleep_data = [
          "sleeperId"             => $sleeper->sleeperId,
          "date"                  => $date,
          "message"               => $sleep_data->message,
          "tip"                   => $sleep_data->tip,
          "avgSleepNumber"        => $avgSleepNumber,
          "avgSleepQuotient"      => $avgSleepQuotient,
          "avgHeartRate"          => $sleep_data->avgHeartRate,
          "avgRespirationRate"    => $sleep_data->avgRespirationRate,
          "totalSleepSessionTime" => $sleep_data->totalSleepSessionTime,
          "inBed"                 => $sleep_data->inBed,
          "outOfBed"              => $sleep_data->outOfBed,
          "restful"               => $sleep_data->restful,
          "restless"              => $sleep_data->restless,
        ];
        if ( $db_sleep_data_id > 0 )
          $MinDB->update( "sleep_datas", $db_sleep_data, "id", $db_sleep_data_id );
        else
          $db_sleep_data_id = $MinDB->insert( "sleep_datas", $db_sleep_data );
         
        // Iterate over sessions 
        foreach( $sleep_data->sleepData as $temp ) {
          foreach( $temp->sessions as $session ) {
          
            // Insert Session
            $db_session_data_id = $MinDB->queryFetchVar( "SELECT id FROM session_datas WHERE sleep_data_id = '{$db_sleep_data_id}' AND startDate = '".$MinDB->escapeString( str_replace('T',' ',$session->startDate) )."' AND endDate = '".$MinDB->escapeString( str_replace('T',' ',$session->endDate) )."'", "id" );
            $db_session_data = [
              "sleep_data_id"         => $db_sleep_data_id,
              "startDate"             => $session->startDate,
              "endDate"               => $session->endDate,
              "longest"               => ( $session->longest ? "Yes" : "No" ),
              "sleepNumber"           => $session->sleepNumber,
              "sleepQuotient"         => $session->sleepQuotient,
              "avgHeartRate"          => $session->avgHeartRate,
              "avgRespirationRate"    => $session->avgRespirationRate,
              "totalSleepSessionTime" => $session->totalSleepSessionTime,
              "inBed"                 => $session->inBed,
              "outOfBed"              => $session->outOfBed,
              "restful"               => $session->restful,
              "restless"              => $session->restless,
            ];
            if ( $db_session_data_id > 0 )
              $MinDB->update( "session_datas", $db_session_data, "id", $db_session_data_id );
            else
              $db_session_data_id = $MinDB->insert( "session_datas", $db_session_data );
          
          }
        } // End Iterate over sessions

      } // End Iterate over dates
    } // End Iterate over sleepers
  } // End Iterate over beds
}
catch( Exception $e )
{
  echo "Error: ".$e->getMessage()."\n";
}


/*
try
{

  $SleepIQ = new SleepIQ\API( $username, $password );
  
  $bed = array_values($SleepIQ->beds())[0];

  $previous_isInBed = $current_isInBed = $bed->statusRight->isInBed ? true : false;
  if ( $current_isInBed ) {
    echo "[".date("r")."] initially in bed\n";
  } else {
    echo "[".date("r")."] initially out of bed\n";  
  }
  do {
    sleep( 60 * 60 );
    $current_isInBed = $bed->statusRight->isInBed ? true : false;
    if ( $previous_isInBed && $current_isInBed ) {
      echo "[".date("r")."] still in bed\n";
      // still in bed
    } else if ( !$previous_isInBed && !$current_isInBed ) {
      echo "[".date("r")."] still out of bed\n";
      // still out of bed
    } else if ( !$previous_isInBed && $current_isInBed ) {
      echo "[".date("r")."] just got into bed\n";
      // just got into bed
    } else if ( $previous_isInBed && !$current_isInBed ) {
      echo "[".date("r")."] just got out of bed\n";
      // just got out of bed
    }
    $previous_isInBed = $current_isInBed;
  } while(true);

//  foreach( $SleepIQ->beds() as $index => $bed )
//    echo $bed->toString();
//  foreach( $SleepIQ->sleepers() as $index => $sleeper )
//    echo $sleeper->toString();
  
}
catch( Exception $e )
{
  echo "Error: ".$e->getMessage()."\n";
}
*/
