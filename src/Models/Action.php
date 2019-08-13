<?php

namespace InmeActivityLogger\Models;

/**
 * Class Action
 * @package InmeActivityLogger\Models
 */
class Action
{
    const VIEW = 1;
    const CREATE = 2;
    const EDIT = 3;
    const DELETE = 4;
    const APPROVE = 5;
    const REJECT = 6;
    const BLOCK = 7;
    const UNBLOCK = 8;
    const PUBLISH = 9;
    const UNPUBLISH = 10;
    const UPLOAD = 11;
    const SELECT = 12;
    const DETACH = 13;
    const EXPORT = 14;
    const SUBSCRIBE = 15;

    /**
     * @param  $action
     * @return string
     */
    public static function getActionName($action)
    {
        switch ($action) {
            case self::VIEW:
                return 'View';
                break;
            case self::CREATE:
                return 'Create';
                break;
            case self::EDIT:
                return 'Edit';
                break;
            case self::DELETE:
                return 'Delete';
                break;
            case self::APPROVE:
                return 'Approve';
                break;
            case self::REJECT:
                return 'Reject';
                break;
            case self::BLOCK:
                return 'Block';
                break;
            case self::UNBLOCK:
                return 'Unblock';
                break;
            case self::PUBLISH:
                return 'Publish';
            case self::UNPUBLISH:
                return 'Unpublish';
            case self::UPLOAD:
                return 'Upload';
            case self::SELECT:
                return 'Select';
            case self::DETACH:
                return 'Detach';
            case self::EXPORT:
                return 'Export';
            default:
                return 'N\A';
                break;
        }
    }
}
