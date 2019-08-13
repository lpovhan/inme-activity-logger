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
    abstract public function getSubjectName($id);

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
     * @return array|bool|string
     */
    abstract public function getEntity($subject, $entityId);

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

    /**
     * @param $oldEntity
     * @param $newEntity
     * @param $type
     *
     * @return array
     */
    private function compareEntities($oldEntity, $newEntity, $type = null)
    {
        $comparison = ['new' => [], 'old' => [], 'compare_message' => ""];
        if (!$oldEntity) {
            foreach ($newEntity??[] as $key => $item) {
                if (!is_array($item) && !empty($item)) {
                    $comparison['compare_message'] .= (($comparison['compare_message'] != "") ? "<br>" : "")
                        . $this->getCreatedLogMessage($key, $item);
                } else {
                    if ($key == 'templateContent' && !empty($item)) {
                        $newContent = $this->transformContent($item);
                        $comparison['compare_message'] .= (($comparison['compare_message'] != "") ? "<br>" : "")
                            . $this->compareContent([], $newContent??[]);
                    } else {
                        $comparison['compare_message'] .= $this->getArrayMessage($key, [], $newEntity[$key]??[]);
                    }
                }
            }
            if ($comparison['compare_message'] == "") {
                unset($comparison['compare_message']);
            }

            return $comparison;
        }
        if (!$newEntity) {
            foreach ($oldEntity as $key => $item) {
                if (!is_array($item) && !empty($item)) {
                    $comparison['compare_message'] .= (($comparison['compare_message'] != "") ? "<br>" : "")
                        . $this->getDeletedLogMessage($key, $item);
                } else {
                    if ($key == 'templateContent' && !empty($item)) {
                        $oldContent = $this->transformContent($item);
                        $comparison['compare_message'] .= (($comparison['compare_message'] != "") ? "<br>" : "")
                            . $this->compareContent($oldContent??[], []);
                    } else {
                        $comparison['compare_message'] .= $this->getArrayMessage($key??[], $item??[], []);
                    }
                }
            }
            if ($comparison['compare_message'] == "") {
                unset($comparison['compare_message']);
            }

            return $comparison;
        }
        if ($oldEntity && $newEntity) {
            foreach ($oldEntity as $key => $value) {
                if ($key == 'template_content' && (!empty($value) || !empty($newEntity[$key]))) {
                    $oldContent = $this->transformContent($value);
                    $newContent = $this->transformContent($newEntity[$key]);
                    $comparison['compare_message'] .= (($comparison['compare_message'] != "") ? "<br>" : "")
                        . $this->compareContent($oldContent??[], $newContent??[]);
                } else {
                    if (!is_array($value) && !is_array($newEntity[$key])) {
                        if ($value != $newEntity[$key]) {
                            $comparison['compare_message'] .= (($comparison['compare_message'] != "") ? "<br>" : "")
                                . $this->getLogMessage($key, $value, $newEntity[$key]);
                        }
                    } else {
                        if ($type == OperationSubject::DRAFT && $key == 'video') {
                            continue;
                        }
                        $comparison['compare_message'] .= $this->getArrayMessage(
                            $key,
                            $value??[],
                            $newEntity[$key]??[]
                        );
                    }
                }
            }
        }
        if ($comparison['compare_message'] == "") {
            unset($comparison['compare_message']);
        }

        return $comparison;
    }

    /**
     * @param $key
     * @param $val
     * @return string
     */
    private function getCreatedLogMessage($key, $val)
    {
        if ($key == 'options') {
            return $this->getCreatedOptionsLog($val);
        }
        return $key . " changed from <strong style='color:red'>empty</strong> to <strong style='white-space: normal;'>"
            . strip_tags($val) . "</strong>";
    }

    /**
     * @param $val
     * @return string
     */
    private function getCreatedOptionsLog($val)
    {
        $str = "";
        foreach ($val as $key => $item) {
            if (is_string($item) || is_numeric($item)) {
                $str = $key .
                    " changed from <strong style='color:red'>empty</strong> to <strong style='white-space: normal;'>"
                    . strip_tags($item) . "</strong><br>";
            } else {
                $str = $this->getCreatedOptionsLog($item);
            }
        }

        return $str;
    }

    /**
     * @param $content
     * @return array|null
     */
    private function transformContent($content)
    {
        $transformed = [];
        if (!$content) {
            return null;
        }
        foreach ($content as $item) {
            $hash = $item['hash']??uniqid();
            unset($item['hash']);
            $itemTransformed = [
                'alias' => $item['alias'],
            ];
            if (in_array($item['alias'], ['img', 'video', 'audio'])) {
                foreach ($item['data'] as $key => $data) {
                    if ($key == $item['alias'] || $key == 'image') {
                        foreach ($data as $key_ => $dataItem) {
                            $itemTransformed[$key_] = $dataItem;
                        }
                    } else {
                        $itemTransformed[$key] = $data;
                    }
                }
            } else {
                foreach ($item['data'] as $key => $data) {
                    $itemTransformed[$key] = $data;
                }
            }
            $transformed[$hash] = $itemTransformed;
        }

        return $transformed;
    }

    /**
     * @param $old
     * @param $new
     * @return string
     */
    private function compareContent($old, $new)
    {
        $messages = "";
        foreach ($old as $key => $item) {
            if (isset($new[$key])) {
                if ($data = $this->compare($item, $new[$key])) {
                    $messages .= $this->getMessageByAlias($item['alias'], $data['old'], $data['new']) . "<br>";
                }
                unset($new[$key]);
            } else {
                $messages .= $this->getMessageByAlias($item['alias'], $item, []) . "<br>";
            }
        }
        foreach ($new as $key => $item) {
            $messages .= $this->getMessageByAlias($item['alias'], [], $item) . "<br>";
        }

        return $messages;
    }

    /**
     * @param $old
     * @param $new
     * @return array|bool
     */
    private function compare($old, $new)
    {
        $data = [];
        foreach ($old as $key => $item) {
            if ($item != $new[$key]) {
                $data['old'][$key] = $item;
                $data['new'][$key] = $new[$key];
            }
        }

        return (count($data) > 0) ? $data : false;
    }

    /**
     * @param $alias
     * @param $old
     * @param $new
     * @return string
     */
    private function getMessageByAlias($alias, $old, $new)
    {
        $message = "";
        switch ($alias) {
            case 'img':
                if (isset($old['path']) || isset($new['path'])) {
                    $message .= "Change image from ";
                    $imageS3 = config('filesystems.disks.s3.image');
                    if (count($old) > 0) {
                        if (strpos($old['path'], $imageS3) !== false) {
                            $path = $old['path'];
                        } else {
                            $path = $imageS3 . $new['path'];
                        }
                        $message .= "<br><img width='140' src='$path'>";
                        unset($old['path']);
                    } else {
                        $message .= "<strong style='color:red'>empty</strong>";
                    }
                    if (count($new) > 0) {
                        if (strpos($new['path'], $imageS3) !== false) {
                            $path = $new['path'];
                        } else {
                            $path = $imageS3 . $new['path'];
                        }
                        $message .= " to <img width='140' src='$path'>";
                        unset($new['path']);
                    } else {
                        $message .= "to <strong style='color:red'>empty</strong>";
                    }
                }
                if (count($old) > 0 || count($new) > 0) {
                    $message .= (($message != "") ? "<br>" : "");
                    $message .= "Change image: <br>";
                    if (count($old) > 0) {
                        foreach ($old as $k => $i) {
                            $message .= $this->getLogMessage($k, $i, $new[$k]??null) . "<br>";
                        }
                    } elseif (count($new) > 0) {
                        foreach ($new as $k => $i) {
                            $message .= $this->getLogMessage($k, $old[$k]??null, $i) . "<br>";
                        }
                    }
                }
                break;
            case 'video':
                if (isset($old['path']) || isset($new['path'])) {
                    $message .= "Change $alias from";
                    if (count($old) > 0) {
                        $path = $old['path'];
                        $message .= "<br><video data-playlist='$path' controls style=';height: 140px;'></video>";
                    } else {
                        $message .= " <strong style='color:red'>empty</strong>";
                    }
                    if (count($new) > 0) {
                        $path = $new['path'];
                        $message .= " to <video data-playlist='$path' controls style=';height: 140px;'></video>";
                    } else {
                        $message .= " to <strong style='color:red'>empty</strong>";
                    }
                }
                break;
            case 'audio':
                if (isset($old['path']) || isset($new['path'])) {
                    $message .= "Change $alias from";
                    if (count($old) > 0) {
                        $path = $old['path'];
                        $message .= "<br><audio controls><source src='$path' type='audio/mp3'></audio>";
                    } else {
                        $message .= " <strong style='color:red'>empty</strong>";
                    }
                    if (count($new) > 0) {
                        $path = $new['path'];
                        $message .= "to <audio controls><source src='$path' type='audio/mp3'></audio>";
                    } else {
                        $message .= " to <strong style='color:red'>empty</strong>";
                    }
                }
                break;
            default:
                if (count($old) > 0 || count($new) > 0) {
                    $message .= "Change $alias: <br>";
                    if (count($old) > 0) {
                        foreach ($old as $k => $i) {
                            $message .= $this->getLogMessage($k, $i, $new[$k]??null) . "<br>";
                        }
                    } elseif (count($new) > 0) {
                        foreach ($new as $k => $i) {
                            $message .= $this->getLogMessage($k, $old[$k]??null, $i) . "<br>";
                        }
                    }
                }
                break;
        }

        return $message;
    }

    /**
     * @param $key
     * @param $old
     * @param $new
     * @return string
     */
    private function getLogMessage($key, $old, $new)
    {
        if ($key == 'alias' || $key == 'options') {
            return "";
        }
        return $key . " changed from <strong " . ((!$old) ? "style='color:red'" : "style='white-space: normal;'") .
            ">" . strip_tags($old??"empty") . "</strong> to <strong " . (((!$new) ? "style='color:red'"
                    : "style='white-space: normal;'") . ">" . strip_tags($new??"empty")) . "</strong>";
    }

    /**
     * @param $key
     * @param $old
     * @param $new
     * @return string
     */
    private function getArrayMessage($key, $old, $new)
    {
        $msg = "";
        switch ($key) {
            case "tags":
                $tagsMsgDel = "";
                $tagsMsgAdd = "";
                foreach ($old as $key => $item) {
                    if (!in_array($item, $new)) {
                        $tagsMsgDel .= "<span class='label label-default'>$item</span> ";
                    }
                }
                foreach ($new as $key => $item) {
                    if (!in_array($item, $old)) {
                        $tagsMsgAdd .= "<span class='label label-info'>$item</span> ";
                    }
                }
                if (!empty($tagsMsgDel) || !empty($tagsMsgAdd)) {
                    if (!empty($tagsMsgDel)) {
                        $msg .= "Tags: <br><div>Deleted tags: <br>$tagsMsgDel</div>";
                    }
                    if (!empty($tagsMsgAdd)) {
                        $msg .= "<br><div>Added tags: <br>$tagsMsgAdd</div>";
                    }
                }
                break;
            case "covers":
                $old = $this->transformCovers($old);
                $new = $this->transformCovers($new);
                if ($old != $new) {
                    $msg .= "<br>Change cover from ";
                    if (count($old) > 0) {
                        $path = $old['path'];
                        $msg .= "<br><img width='140' src='$path'>";
                        unset($old['path']);
                    } else {
                        $msg .= "<strong style='color:red'>empty</strong>";
                    }
                    if (count($new) > 0) {
                        $path = $new['path'];
                        $msg .= " to <img width='140' src='$path'>";
                        unset($new['path']);
                    } else {
                        $msg .= "to <strong style='color:red'>empty</strong>";
                    }
                }
                break;
            case "authors":
                $old = $this->transformAuthor($old);
                $new = $this->transformAuthor($new);
                $authorMsgDel = "";
                $authorMsgAdd = "";
                foreach ($old as $key => $item) {
                    if (!in_array($item, $new)) {
                        $authorMsgDel .= "<span class='label label-default'>$item</span> ";
                    }
                }
                foreach ($new as $key => $item) {
                    if (!in_array($item, $old)) {
                        $authorMsgAdd .= "<span class='label label-info'>$item</span> ";
                    }
                }
                if (!empty($authorMsgDel) || !empty($authorMsgAdd)) {
                    if (!empty($authorMsgDel)) {
                        $msg .= "Tags: <br><div>Deleted author: <br>$authorMsgDel</div>";
                    }
                    if (!empty($authorMsgAdd)) {
                        $msg .= "<br><div>Added author: <br>$authorMsgAdd</div>";
                    }
                }
                break;
            case "video":
                $old = $this->transformVideo($old);
                $new = $this->transformVideo($new);
                if ($old || $new ?? ($old != $new)) {
                    $msg .= "<br>Change video from ";
                    if ($old) {
                        $msg .= "<br><video data-playlist='$old' controls style=';height: 140px;'></video>";
                    } else {
                        $msg .= " <strong style='color:red'>empty</strong>";
                    }
                    if ($new) {
                        $msg .= " to <video data-playlist='$new' controls style=';height: 140px;'></video>";
                    } else {
                        $msg .= " to <strong style='color:red'>empty</strong>";
                    }
                }
                break;
        }

        return $msg;
    }

    /**
     * @param $item
     * @return array
     */
    private function transformCovers($item)
    {
        if ($item && isset($item['thumb'])) {
            return [
                'path' => $item['thumb']??null,
            ];
        }

        return [];
    }

    /**
     * @param $item
     * @return array
     */
    private function transformAuthor($item)
    {
        $data = [];
        if (!empty($item)) {
            foreach ($item as $author) {
                $data[] = $author['firstname'] . ' ' . $author['lastname'];
            }
            return $data;
        }
        return $data;
    }

    /**
     * @param $item
     * @return null
     */
    private function transformVideo($item)
    {
        return $item['url']??null;
    }

    /**
     * @param $key
     * @param $val
     * @return string
     */
    private function getDeletedLogMessage($key, $val)
    {
        if ($key == 'options') {
            return $this->getDeletedOptionsLog($val);
        }
        return $key . " changed from <strong style='white-space: normal;'>" . strip_tags($val) .
            "</strong> to <strong style='color:red'>empty</strong>";
    }

    /**
     * @param $val
     * @return string
     */
    private function getDeletedOptionsLog($val)
    {
        $str = "";
        foreach ($val as $key => $item) {
            if (is_string($item) || is_numeric($item)) {
                $str = $key . " changed from <strong style='white-space: normal;'>" . strip_tags($item) .
                    "</strong> to <strong style='color:red'>empty</strong><br>";
            } else {
                $str = $this->getDeletedOptionsLog($item);
            }
        }

        return $str;
    }
}
