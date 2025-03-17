<?php

namespace Kanboard\Plugin\WechatWorkNotifier\Helper;

use Kanboard\Core\Base;

class MessageHelper extends Base
{
    private $lastRoundAudiences = array();
    private $lastRoundTime = 0;
    private $notificationInterval = 1;

    public function __construct($c)
    {
        parent::__construct($c);
        if (!empty($GLOBALS['WWN_CONFIGS']['NOTIFICATION_INTERVAL'])) {
            $this->notificationInterval = $GLOBALS['WWN_CONFIGS']['NOTIFICATION_INTERVAL'];
        }
    }

    public function send($audiences, $message)
    {
        $result = false;

        if ($this->getToken()) {
            $result = $this->doSend($this->getToken(), $audiences, $message);
        }

        if (!$result) {
            $result = $this->doSend($this->getToken(true), $audiences, $message);
        }
        return $result;
    }

    public function getAudiences($project, $eventData, $assigneeOnly = false)
    {
        $audiences = array();

        // $owner = $this->userModel->getById($eventData["task"]["owner_id"]);

        // if (!empty($owner) && !empty($owner['username']))
        // {
        //     $audiences[] = $owner['username'];
        // }
        // $this->logger->debug(json_encode(["line" => "43", "audiences" => $audiences], JSON_UNESCAPED_UNICODE));
        if (!$assigneeOnly) {
            // $projectOwner = $this->userModel->getById($project["owner_id"]);
            // if (!empty($projectOwner) && !empty($projectOwner['username']))
            // {
            //     $audiences[] = $projectOwner['username'];
            // }
            // $this->logger->debug(json_encode(["line" => "51", "audiences" => $audiences, "assigneeOnly" => $assigneeOnly], JSON_UNESCAPED_UNICODE));
            $creator = $this->userModel->getById($eventData["task"]["creator_id"]);
            // if (!empty($creator) && !empty($creator['username'])) {
            //     $audiences[] = $creator['username'];
            // }
            // $this->logger->debug(json_encode(["line" => "57", "audiences" => $audiences, "creator"=> $creator], JSON_UNESCAPED_UNICODE));
            // $multimembers = isset($this->multiselectMemberModel) ? $this->multiselectMemberModel->getMembers($eventData["task"]['owner_ms']) : null;
            // $this->logger->debug(json_encode(["line" => "58", "audiences" => $audiences, "eventData" => $eventData], JSON_UNESCAPED_UNICODE));
            // if (!empty($multimembers)) {
            //     foreach ($multimembers as $member) {
            //         $user = $this->userModel->getById($member['id']);
            //         if (!empty($user['username'])) {
            //             $audiences[] = $user['username'];
            //         }
            //     }
            // }
            // $groupmembers = isset($this->groupMemberModel) ? $this->groupMemberModel->getMembers($eventData["task"]['owner_gp']) : null;
            // $this->logger->debug(json_encode(["line" => "68", "audiences" => $audiences, "groupmembers" => $groupmembers], JSON_UNESCAPED_UNICODE));
            // if (!empty($groupmembers)) {
            //     foreach ($groupmembers as $member) {
            //         $user = $this->userModel->getById($member['id']);
            //         if (!empty($user['username'])) {
            //             $audiences[] = $user['username'];
            //         }
            //     }
            // }
            $projectmembers = $this->projectUserRoleModel->getUsers($eventData["task"]["project_id"]);
            if (!empty($projectmembers)) {
                foreach ($projectmembers as $member) {
                    $user = $this->userModel->getById($member['id']);
                    if (!empty($user['username']) && $user['username'] != $creator['username']) {
                        $audiences[] = $user['username'];
                    }
                }
            }
            // $this->logger->debug(json_encode(["line" => "87", "audiences" => $audiences, "projectmembers" => $projectmembers], JSON_UNESCAPED_UNICODE));
        }
        $this->logger->debug(json_encode(["line" => "89", "audiences" => $audiences], JSON_UNESCAPED_UNICODE));
        return array_unique($audiences);
    }

    public function getTaskLink($taskId, $commentId = null)
    {
        $taskLink = $this->getKanboardURL() . "/task/" . $taskId;
        if (!empty($commentId)) {
            $taskLink .= "#comment-" . $commentId;
        }
        return $taskLink;
    }

    public function getProjectLink($projectId)
    {
        return $this->getKanboardURL() . "/board/" . $projectId;
    }

    private function getKanboardURL()
    {
        $url = $GLOBALS["WWN_CONFIGS"]["KANBOARD_URL"];
        if (strrpos($url, '/', -1) == strlen($url) - 1) {
            $url = substr($url, 0, -1);
        }
        return $url;
    }

    private function doSend($token, $audiences, $jsonTemplate)
    {
        if ($token) {
            $prevAudiences = $this->lastRoundAudiences;
            // try
            try {
                $jsonTemplate["touser"] = implode("|", $this->getFilteredAudiencesAndSetLast($audiences));
                // send message
                // $result = $this->httpClient->doRequestWithCurl(
                //     'POST',
                //     "https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token=".$token,
                //     json_encode($jsonTemplate, JSON_UNESCAPED_UNICODE),
                //     ['Content-type: application/json']
                // );
                // $result = json_decode($result);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token=" . $token);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonTemplate, JSON_UNESCAPED_UNICODE));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                // 设置代理
                curl_setopt($ch, CURLOPT_PROXY, 'sz2.ziiyc.com');
                curl_setopt($ch, CURLOPT_PROXYPORT, 47999);
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, 'batmobi:Kc4BnCbme6XP7H'); // 如果需要认证
                $this->logger->debug(json_encode($jsonTemplate, JSON_UNESCAPED_UNICODE));
                $result = curl_exec($ch);
                curl_close($ch);
                // result
                $result = json_decode($result);
                if ($result->errcode == 0) {
                    return true;
                } else {
                    $this->lastRoundAudiences = $prevAudiences;
                    $this->logger->debug(serialize($result));
                }
            }
            // catch error
            catch (Exception $error) {
                $this->lastRoundAudiences = $prevAudiences;
                $this->logger->debug(serialize($error));
            }
        }
        return false;
    }

    private function getFilteredAudiencesAndSetLast($audiences)
    {
        $time = time();
        if ($time - $this->lastRoundTime > $this->notificationInterval) {
            $this->lastRoundTime = $time;
            $this->lastRoundAudiences = $audiences;
            return $audiences;
        } else {
            $newAudiences = array_diff($audiences, array_intersect($this->lastRoundAudiences, $audiences));
            $this->lastRoundAudiences = array_merge($this->lastRoundAudiences, $newAudiences);
            return $newAudiences;
        }
    }

    private function getToken($force = false)
    {
        if (!session_exists("WWN_TOKEN") || $force) {
            $token = $this->getRemoteToken(
                $GLOBALS["WWN_CONFIGS"]["CORPID"],
                $GLOBALS["WWN_CONFIGS"]["SECRET"]
            );

            if ($token) {
                session_set("WWN_TOKEN", $token);
            }
        }
        return session_get("WWN_TOKEN");
    }

    private function getRemoteToken($corpid, $secret)
    {
        try {
            $data = $this->httpClient->getJson("https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=" . $corpid . "&corpsecret=" . $secret);
            if (isset($data["access_token"])) {
                return $data["access_token"];
            }
        } catch (Exception $error) {
            $this->logger->debug(serialize($error));
        }
        return "";
    }
}
