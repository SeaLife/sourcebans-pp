<?php

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
if (!defined('API_KEY')) {
    echo "You need to setup a API_KEY in order to use this API";
    echo "\n";
    echo "Open up config.php in the document root of SourceBans++ and add the following line:";
    echo "\n\n";
    echo 'define("API_KEY", "someSecureApiKey");';
    die();
}

global $userbank;

$requestType = filter_input(INPUT_GET, 'rt');

/** @var $db Database */
$db = $GLOBALS['PDO'];

header("Content-Type", "application/json");

function printErr ($msg, $status) {
    http_response_code($status);

    $data = array(
        'status'  => $status,
        'message' => $msg,
    );

    echo json_encode($data);

    die();
}

function assertNotEmpty ($key, $default = NULL) {
    $item = trim(filter_input(INPUT_GET, $key));

    if (empty($item) && !is_numeric($item) && is_null($default)) printErr($key . ' cannot be empty', 400);
    return empty($item) ? $default : $item;
}

function getAdminById ($id) {
    global $userbank;
    $result = NULL;
    foreach ($userbank->GetAllAdmins() as $admin) {
        if ($admin["aid"] == $id) {
            $result = $admin;
        }
    }
    return $result;
}

/** ACCESS CHECK */
$headers  = getallheaders();
$apiToken = @$headers["X-Api-Token"];
if (empty($apiToken) && API_KEY != 'NO_KEY') {
    printErr("Missing Header-Field: X-Api-Token", 401);
}
if (API_KEY != 'NO_KEY' && API_KEY != $apiToken) {
    printErr("Access Denied", 403);
}

switch ($requestType) {
    default:
        echo "Unknown or no Request-Type set.";
        break;
    case 'fetch-all':
        $db->query("SELECT b.*, a.user as adminName FROM :prefix_bans b LEFT JOIN :prefix_admins a ON a.aid = b.aid");
        $data = $db->resultset();
        echo json_encode($data);
        break;
    case 'fetch-banned':
        $db->query("SELECT b.*, a.user as adminName FROM :prefix_bans b LEFT JOIN :prefix_admins a ON a.aid = b.aid WHERE RemovedOn IS NULL");
        $data = $db->resultset();
        echo json_encode($data);
        break;
    case 'add-ban':

        $steam        = assertNotEmpty('user-steam-id');
        $uip          = assertNotEmpty('user-ip');
        $name         = GetCommunityName($steam);
        $adminSteamID = assertNotEmpty('admin-steam-id');
        $length       = assertNotEmpty('length', 0);
        $reason       = assertNotEmpty('reason', '');
        $sid          = assertNotEmpty('sid', 0);

        $db->query("SELECT * FROM :prefix_bans WHERE authid = :authid AND RemoveType IS NULL");
        $db->bind(":authid", $steam);

        $singleItem = $db->single();

        if (!$singleItem) {

            $db->query("INSERT INTO `:prefix_bans` (`created`, `authid`, `ip`, `name`, `ends`, `length`, `reason`, `aid`, `adminIp`, `type`, `sid`)
                    VALUES (UNIX_TIMESTAMP(), :authid, :ip, :name, (UNIX_TIMESTAMP() + :length), :length, :reason, :aid, '', 0, :sid)");

            $aid = 0;
            foreach ($userbank->GetAllAdmins() as $admin) {
                if ($admin["authid"] == $adminSteamID) {
                    $aid = $admin["aid"];
                }
            }

            $db->bindMultiple([
                ':authid' => $steam,
                ':name'   => $name,
                ':aid'    => $aid,
                ':ip'     => $uip,
                ':reason' => $reason,
                ':length' => $length * 60,
                ':sid'    => $sid,
            ]);

            $db->execute();

            echo json_encode(array(
                "bid" => $db->lastInsertId(),
            ));
        } else {
            $db->query("UPDATE :prefix_bans SET ends = (UNIX_TIMESTAMP() + :length), length = :length, reason = :reason WHERE bid = :bid");
            $db->bindMultiple([
                ":length" => $length * 60,
                ":bid"    => $singleItem["bid"],
                ":reason" => $reason,
            ]);
            $db->execute();

            printErr("User is already banned, updated.", 200);
        }

        break;
    case 'undo-ban':
        $steam = assertNotEmpty('user-steam-id');

        $db->query("SELECT * FROM :prefix_bans WHERE authid = :authid AND RemoveType IS NULL");
        $db->bind(":authid", $steam);

        $singleItem = $db->single();

        if ($singleItem) {
            $db->query("UPDATE :prefix_bans SET RemoveType = 'U', RemovedBy = 0, RemovedOn = UNIX_TIMESTAMP() WHERE bid = :bid");
            $db->bind(":bid", $singleItem["bid"]);
            $db->execute();

            if ($db->rowCount() == 1) {
                echo json_encode(array("message" => "ok"));
            } else {
                printErr("Failed to unban the user!", 500);
            }
        } else {
            printErr("User does not seems to be banned", 400);
        }

        break;
    case 'prune-bans':
        PruneBans();
        break;
}