<?php

namespace InmeActivityLogger;

use InmeActivityLogger\Models\OperationStatus;

/**
 * Class LogActivity
 * @package InmeActivityLogger
 */
trait LogActivity
{
    private $activityObjectId;
    private $activityObjectType;
    private $activitySubjectId;
    private $activitySubjectType;
    private $activityAction;
    private $activityStatus;

    /**
     * @param string $message
     */
    public function saveActivity(string $message)
    {
        switch ($this->activityStatus) {
            case OperationStatus::SUCCESS:
                info($message, $this->getActivityExtra());
                break;
            case OperationStatus::FAILED_500:
                app('log')->error($message, $this->getActivityExtra());
                break;
            default:
                break;
        }
    }

    /**
     * @return array
     */
    public function getActivityExtra()
    {
        return $this->getActivityObject()
            + $this->getActivitySubject()
            + $this->getActivityAction()
            + $this->getActivityStatus();
    }

    /**
     * @param $message
     */
    public function activity($message)
    {
        $this->saveActivity($message);
    }

    /**
     * @param $objectId
     * @return $this
     */
    public function activityObjectId($objectId)
    {
        $this->activityObjectId = $objectId;

        return $this;
    }

    /**
     * @param string $objectType
     * @return $this
     */
    public function activityObjectType(string $objectType)
    {
        $this->objectType = $objectType;

        return $this;
    }

    /**
     * @param int $status
     * @return $this
     */
    public function activityStatus(int $status)
    {
        $this->activityStatus = $status;

        return $this;
    }

    /**
     * @return $this
     */
    public function activityStatusFailed()
    {
        $this->activityStatus = OperationStatus::FAILED_500;

        return $this;
    }

    /**
     * @return $this
     */
    public function activityStatusSuccess()
    {
        $this->activityStatus = OperationStatus::SUCCESS;

        return $this;
    }

    /**
     * @return array
     */
    private function getActivityObject()
    {
        return [
            'objectId' => $this->activityObjectId ?? null,
            'objectType' => $this->objectType ?? self::ACTIVITY_OBJECT_TYPE ?? null
        ];
    }

    /**
     * @return array
     */
    private function getActivityAction()
    {
        return [
            'action' => $this->activityAction ?? self::ACTIVITY_ACTION ?? null
        ];
    }

    /**
     * @return array
     */
    private function getActivityStatus()
    {
        return [
            'status' => $this->activityStatus ?? null
        ];
    }

    /**
     * @return array
     */
    private function getActivitySubject()
    {
        return [
            'subjectType' => $this->activitySubjectType ?? self::ACTIVITY_SUBJECT_TYPE ?? null
        ];
    }
}
