<?php


namespace Ajslim\FencingActions\Utility;



/**
 * Api Controller
 */
class Utility
{
   public static function calculateBinaryConfidenceInterval($success, $sampleSize)
   {
       if ($success > $sampleSize) {
           return 0;
       }

       if ($success <= 100 || $sampleSize <= 100) {
           $binaryConfidenceIntervals100Array = require(__DIR__ . '/binary-confidence-intervals-100.php');
           return $binaryConfidenceIntervals100Array[$sampleSize-1][$success-1];
       }

       $p = $success / $sampleSize;

       return $p - (1.96 * sqrt( ($p * (1 - $p)) / $sampleSize  ));
   }

}
