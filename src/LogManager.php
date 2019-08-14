<?php

namespace InmeActivityLogger;

use Illuminate\Database\Eloquent\Collection;
use InmeActivityLogger\Models\Action;
use InmeActivityLogger\Models\OperationStatus;
use League\Fractal\TransformerAbstract;

/**
 * Class LogManager
 * @package InmeActivityLogger
 */
abstract class LogManager
{
    /**
     * @param Collection $entity
     * @param TransformerAbstract $transformer
     * @return array
     */
    protected static function getItemDetails($entity, $transformer)
    {
        if ($entity instanceof Collection && !isset($entity->id)) {
            $result = [];
            $entities = $entity->all();
            foreach ($entities as $item) {
                $result[] = (new $transformer)->transform($item);
            }
        } else {
            $result = (new $transformer)->transform($entity);
        }
        return $result;
    }

    /**
     * @param $subjectId
     * @param $actionId
     * @param $statusId
     * @param null $data
     * @param null $entityId
     * @return int
     * @throws \Exception
     */
    public function save($subjectId, $actionId, $statusId, $data = null, $entityId = null)
    {
        $nickname = request()->header('Crm-user');
        $subject = self::getSubjectName($subjectId);
        $action = Action::getActionName($actionId);
        $status = OperationStatus::getStatusName($statusId);
        if (($data || $actionId == Action::CREATE) && $statusId == OperationStatus::SUCCESS) {
            if ($actionId != Action::CREATE && $actionId != Action::UPLOAD) {
                $oldData = $this->getDataFromEntity($data, $subjectId);
            } else {
                $oldData = [];
            }
            if ($actionId != Action::DELETE) {
                $newData = $this->getEntityBySubject($subjectId, $entityId);

                $comparison = $this->getDiff($oldData, $newData);
                if ($entityId) {
                    $comparison['subject_id'] = $entityId;
                }

                $comparison['subject'] = $subjectId;
                $comparison['status'] = $statusId;
                info("User $nickname: $action $subject with ID $entityId. Status $status", $comparison);
            } else {
                $comparison = $this->getDiff($oldData, []);
                info("User $nickname: $action $subject with ID $entityId. Status $status", $comparison);
            }
        } else {
            if (isset($data['code']) && isset($data['message'])) {
                info(
                    "User $nickname: $action $subject with status $status",
                    [
                        'code' => $data['code'],
                        'message' => $data['message']
                    ]
                );
            } else {
                $msg = ($entityId) ? " with ID $entityId. Status $status " : "s list with status $status";
                info("User $nickname: $action $subject" . $msg);
            }
        }

        return 1;
    }

    /**
     * @param $id
     * @return mixed
     */
    abstract public static function getSubjectName($id);

    /**
     * @param $entity
     * @param $type
     * @return array|null
     * @throws \Exception
     */
    abstract protected function getDataFromEntity($entity, $type);

    /**
     * @param $subject
     * @param $entityId
     * @return array|null
     * @throws \Exception
     */
    public function getEntityBySubject($subject, $entityId)
    {
        $entity = $this->getEntity($subject, $entityId);
        return $this->getDataFromEntity($entity, $subject);
    }

    /**
     * @param $subject
     * @param $entityId
     * @return array|bool|string
     */
    abstract public function getEntity($subject, $entityId);

    /**
     * @param $old
     * @param $new
     *
     * @return array
     */
    private function getDiff($old, $new)
    {
        $old_values = $this->convertToArray($old);
        $new_values = $this->convertToArray($new);

        $data = [];

        if (is_array($old_values) && is_array($new_values)) {
            $diff = self::arrayDiffAssoc($new_values, $old_values);
            if (empty($diff)) {
                $diff = self::arrayDiffAssoc($old_values, $new_values);
            }

            $diff_values = [];

            if (!empty($diff)) {
                foreach ($diff as $key => $value) {
                    $diff_values[$key] = $key;
                }

                $data = ['old' => [], 'new' => []];
                foreach ($diff_values as $key => $value) {
                    if (!empty($old_values)) {
                        $data['old'][$key] = $old_values[$key];
                    }
                    if (!empty($new_values)) {
                        $data['new'][$key] = $new_values[$key];
                    }
                }

                return $data;
            }
        }

        return $data;
    }

    /**
     * @param $array
     * @return array
     */
    private function convertToArray($array)
    {
        if (!is_array($array) && !is_object($array)) {
            return $array;
        }

        $result = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->convertToArray($value);
            } elseif (is_object($value)) {
                $new_array = (array)$value;

                $data = [];

                foreach ($new_array as $key2 => $value2) {
                    $data[$key2] = $this->convertToArray($value2);
                }
                $result[$key] = (array)$data;
            } else {
                $result_json = json_decode($value, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $result[$key] = $result_json;
                } else {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * @param $arr1
     * @param $arr2
     * @return array
     */
    private static function arrayDiffAssoc($arr1, $arr2)
    {
        $result = [];
        foreach ($arr1 as $k => $v) {
            if (is_array($v)) {
                if (array_key_exists($k, $arr2)) {
                    $diff = self::arrayDiffAssoc($v, $arr2[$k]);
                    if (!empty($diff)) {
                        $result[$k] = $diff;
                    }
                } else {
                    $result[$k] = $v;
                }
            } else {
                if (!array_key_exists($k, $arr2) || (array_key_exists($k, $arr2) && $v !== $arr2[$k])) {
                    $result[$k] = $v;
                }
            }
        }
        return $result;
    }
}
