<?php

namespace InmeActivityLogger\Models;

/**
 * Class OperationStatus
 * @package InmeActivityLogger\Models
 */
class OperationStatus
{

    const SUCCESS = 1;
    const FAILED_500 = 2;
    const NOT_ALLOWED = 3;

    /**
     * @param  $status
     * @return string
     */
    public static function getStatusName($status)
    {
        switch ($status) {
            case self::SUCCESS:
                return 'Success';
                break;
            case self::NOT_ALLOWED:
                return 'Not allowed';
                break;
            case self::FAILED_500:
                return 'Failed 500';
                break;
            default:
                return 'N\A';
                break;
        }
    }
}
