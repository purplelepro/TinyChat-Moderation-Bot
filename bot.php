<?PHP
$own_user_id = null;
$userlist = array(); 
$useraccts = array();
$oper = array();
$nick = array();
$bothasmod = false;     // Making this true wont make it mod lol.

/* Measures against spambots. */
$suspectspammer = array(            // Against link and snapshot spammers.
    'linkspammer'   => array(),
    'snapspammer'   => array()
);
$bannedwords = array();
$camban = false;                // Against cambot spammers.
$safeusers = array(
    'list'      => array(),          // Stores a safelist of current users, when !autoban activated.
    'time'      => 0,           // Next time(), in seconds, until next check against safelist.
    'repeat'    => 3            // How many checks to do.
);
$autoban = array(
    'mode'  => false,
    'count' => 0,
    'time'  => 0,
    'list'  => array()
);
$joins = array(
    'count'     => 0,           // Count joins.
    'time'      => 0            // If more than 20 users join in 30 seconds, activate autoban!
);
$cammer = array(            // Catches bots that spam cam up down.
    'id'    => '',
    'time'  => '',
    'count' => ''
);
$androidban = false;
$iphoneban = false;         // NOT IMPLEMENTED, need packet
$lastmsgs = array();            
$bannedusers = array();         
$colors = array(
       'blue'       => '#1965b6',
       'cyan'       => '#32a5d9',
       'lightgreen' => '#7db257',
       'yellow'     => '#a78901',
       'pink'       => '#9d5bb5',
       'purple'     => '#5c1a7a',
       'red'        => '#c53332',
       'darkred'    => '#821615',
       'green'      => '#487d21',
       'lightpink'  => '#c356a3',
       'lightblue'  => '#1d82eb',
       'turquoise'  => '#a990',
       'skinpink'   => '#b9807f',
       'leafgreen'  => '#7bb224',
       // Non-official TC colors...
       'black'      => '#000000'
);
$color = $colors['black']; // Default color for messages.

//**DONT TOUCH**
ini_set('memory_limit', '-1');
ini_set('max_execution_time', '999999999999999999999999999999999999999999999999');
 
// change to the proper directory
chdir("./RtmpClient");
// the rtmp client library
require_once "RtmpClient.class.php";
// go back
chdir("../");

date_default_timezone_set("America/Chicago"); // For all time() uses, otherwise it goes mad.

//var_dump($argv); // get cmd line args, $argv[0] is always the script full file name.

$sends = array();
$recvs = array();

$running = true;

$client = new RtmpClient();
$client->connect($rtmp, "tinyconf", $port, array($roomname, $autoop, "show", "tinychat", $BotUsername));

runClient();

function runClient() {
    global $running, $sends, $recvs, $client, $safeusers;
    
    while ($running) {
            // Critical events, check before bot receive/send! Better to avoid these.
            if (false/*$safeusers['time'] > 0*/) {
                $time = time();
                
                if ($time - $safeusers['time'] > 20) {
                    $safeusers['time'] = 0;
                    // not finished meh... supposed to ban anyone not in safelist,
                    // but only one every while loop!
                }
            }
            
            // proccess recvs
            foreach ($recvs as $i => $recv) {
                    // check for commandName in the packet
                    if (array_key_exists("commandName", $recv)) {
                            // grab packet command
                            $command = $recv->commandName;
                            // grab args
                            $args = $recv->arguments;
                            // get packet
                            $packet = $recv->getPacket();
                            // grab packet payload
                            $payload = $packet->payload;
                            
                            // call function to process response
                            // this is where you would respond to packets.
                            processCommmand($command, $args, $payload);
                    } else {
                        error_log("NON-CMD PACKET:");
                        print_r($recv);
                    }
                    
                    unset($recvs[$i]);
            }
            
            // send queue
            foreach ($sends as $i => $send) {
                    $call = $send["call"];
                    $args = $send["arguments"];
                    //echo ">>>>>>>>$call\n";
                    //print_r($args);
                    
                    $recv = $client->call($call, $args);
                    if (!is_null($recv))
                            $recvs[] = $recv;
                    
                    unset($sends[$i]);
            }
     
            // listen recv
            try {
                    $recv = $client->listen();
                    if (!is_null($recv))
                            $recvs[] = $recv;
            }
            catch (Exception $e) {
                    //print_r($e);  Goes into a loop of error reports, freezes the bot even for warnings.
                    exit();
            }
    }
    
    // After while closes, on error, or $running = false.
    $client->close();
}

function queueSend($call, $arguments) {
        global $sends;
        
        if (!isset($call) || empty($call) || empty($arguments) || !isset($arguments)) {
            error_log('Empty arguments for send!');
            return;
        }
        
        $sends[] = array(
                "call" => $call,
                "arguments" => $arguments
        );
}

function processCommmand($event, $arguments, $_payload) {
        global $own_user_id, $BotUsername, $botname, $roomname, $prohash, $nickname, $user_id, $userlist, 
                $nick, $hasmod, $request, $tempmods, $suspectspammer, $bothasmod, $bannedwords, 
                $camban, $colors, $color, $safeusers, $autoban, $joins, $androidban, $client, 
                $cammer, $running, $bannedusers, $useraccts, $GreetUsers;
                
        // This will read the payload from the rtmp data types.
        $payload = readPayload($_payload);
        
        switch ($event) {
                case "onBWDone":
                        error_log("-- onBWDone event.");
                        // this doesn't matter its just one of the commands that gets called
                        // usually only useful in flash itself not here
                        // but it's included to avoid the default stuff printing
                        break;
                
                // Users on cam, when bot joins.
                case "avons":
                    error_log("-- avons events.");
                    //echo(implode(",", $payload));
                    break;
                    
                case "quit":
                    if (isset($payload[0])) $nickname = $payload[0];
                    
                    if (isset($payload[1])) $id = $payload[1];
                    
                    // Remove from userlist, if added even.
                    if (isset($id) && !empty($id)) {
                        if (isset($userlist[$id])) unset($userlist[$id]);
                    }
                    
                    if (isset($nickname)) {
                        error_log('-- ' . $nickname . ' has quit.');
                    } else {
                        error_log('-- unknown quit event.');
                    }
                    break;
                    
                case "kick":
                    if (isset($payload[0]) && isset($payload[1])) {
                        $name = $payload[1];
                        $id = $payload[0];
                        
                        // Save banned users in array, for forgive command.
                        $bannedusers[$name] = $id;  // Probable bug: No duplicates possible.
                        
                        error_log('-- ' . $name . ' has been kicked.');
                    } else {
                        error_log('-- unknown kick event:');
                        print_r($payload);
                    }
                    break;
                
                case "banned":
                    error_log('-- Bot has been banned!');
                    break;
                    
                case "pros":
                    error_log('-- pros event.');
                    break;
                    
                case "from_owner":
                    error_log('-- from_owner event:');
                    print_r($payload);
                    break;
                    
                case "owner":
                    error_log('-- owner event.');
                    break;
                
                case "registered":
                    error_log('-- registered event.');
                    break;
                
                case "topic":
                    if (isseT($payload[0])) {
                        error_log('-- Topic: ' . $payload[0]);
                    } else {
                        error_log('-- topic event.');
                    }
                    break;
                
                case "notice":
                    if (!isset($payload[0])) {
                        error_log("-- Unknown notice event: " . implode(", ", $payload));
                        return;
                    }
                    
                    switch($payload[0]) {
                        // User goes on cam.
                        case 'avon':
                            $nickname = $payload[2];
                            $id = $payload[1];
                            
                            // If user is [normal] mod.
                            $curhasmod = false;
                            $userid = array_search($nickname, $userlist);
                            if ($hasmod[$userid] == 1) {
                                $curhasmod = true;
                            }
                            
                            // If user is in tempmod array.
                            $curhastempmod = false;
                            if ($userid !== false) {
                                if (in_array($nickname, $tempmods)) {
                                    $curhastempmod = true;
                                }
                            }
                            
                            //$cammer id name time, except tempmods and mods.
                            if ($bothasmod && !$curhasmod && !$curhastempmod) {
                                // if id = lastid and count > 2 and time under 20 sec then ban
                                $time = time();
                                if ($cammer['id'] == $id && $cammer['count'] >= 3 && $time - $cammer['time'] < 20) {
                                    queueSend("kick", [$nickname, $id]);
                                    queueSend("privmsg", [textToDec("Banned *$nickname* for cam-spamming."), "$color,en"]);
                                    return;
                                } elseif ($cammer['id'] == $id) {
                                    $cammer['count'] = $cammer['count'] + 1;
                                } else {
                                    $cammer['id'] = $id;
                                    $cammer['count'] = 1;
                                }
                            }
                            
                            if ($bothasmod && $camban && !($curhasmod || $curhastempmod)) {
                                queueSend("kick", [$nickname, $id]);
                                return;
                            }
                            
                            break;
                        
                        case 'pro':
                            $id = isset($payload[1]) ? $payload[1] : 'unknown';
                            
                            error_log('-- ' . $id . ' is a pro.');
                            break;
                        
                        default:
                            error_log("-- Unknown notice event: " . implode(", ", $payload));
                    }
                    break;
                
                // For all messages, bot cmds, and some other broadcasted events.
                case "privmsg":
                    $to = $payload[0];
                    $message = decToText($payload[1]);
                    $cclang = explode(",", $payload[2]);
                    $nickname = $payload[3];
                    
                    echo "$nickname: $message\n";
                    
                    // If user is [normal] mod.
                    $curhasmod = false;
                    $userid = array_search($nickname, $userlist);
                    if ($userid !== false) {
                        if ($hasmod[$userid] == 1) {
                            $curhasmod = true;
                        }
                    }
                    
                    // If user is in tempmod array.
                    $curhastempmod = false;
                    if (in_array($nickname, $tempmods)) {
                        $curhastempmod = true;
                    }
                    
                    // Respond to private messages to bot, or any broadcasted / cmds.
                    if ($message[0] == "/") {
                        $parts = explode(" ", $message);
                        $command = array_shift($parts);
                        $text = implode(" ", $parts);
                        
                        switch ($command) {
                            case '/msg':
                                $msgparts = array_slice($parts, 1);
                                $cmd = array_shift($msgparts);
                                $msg = implode(' ', $msgparts);
                                
                                // Override cmd anyone can use, to check if bot is fucking up modlist.
                                if ($cmd == '?hasmod' || $cmd == '?hasmods') {
                                    $hasmodidarray = array_keys($hasmod, 1);
                                    $hasmodlist = array();
                                    foreach($hasmodidarray as $id) {
                                        if (isset($userlist[$id])) {
                                            $name = $userlist[$id];
                                            $hasmodlist[] = $name;
                                        }
                                    }
                                    
                                    $hasmodlist = implode(', ', $hasmodlist);
                                    error_log('Current mods: ' . $hasmodlist);
                                    queueSend("privmsg", [textToDec('/msg ' . $nickname . ' The following have mod: *' . $hasmodlist . '*.') , "$color,en"]);
                                    return;
                                }
                                
                                if ($cmd == '?cmd' || $cmd == '?cmds' || $cmd == '?command' || 
                                                        $cmd == '?commands' || $cmd == '?help') {
                                    $rnd = array();
                                    
                                    for ($i = 0; $i < 10; $i++) {
                                        $rnd[] = rand(0, count($commands) - 1);
                                    }
                                    
                                    $rnd = array_unique($rnd); // Don't need duplicates.
                                    
                                    $cmds = array();
                                    foreach ($rnd as $val) {
                                        $cmds[] = $commands[$val];
                                    }
                                    
                                    queueSend("privmsg", [textToDec('/msg ' . $nickname . ' Try these commands: *' . implode(", ", $cmds)) . '*.', "$color,en"]);
                                }
                                
                                // Commands sent privately to bot.
                                if ($cmd[0] == '!') {
                                    if ($curhasmod) {
                                        switch ($cmd) {
                                            // !say MSG
                                            case '!say':
                                                queueSend("privmsg", [textToDec($msg) , "$color,en"]);
                                                break;
                                                
                                            // !saycolor COLORNAME MSG
                                            case '!saycolor':
                                                $ccolor = array_shift($msgparts);
                                                // Either get the color by name, or return default black.
                                                $ccolor = isset($colors[$ccolor]) ? $colors[$ccolor] : $colors['black'];
                                                $msg = implode(' ', $msgparts);
                                                queueSend("privmsg", [textToDec($msg) , "$ccolor,en"]);
                                                break;
                                            
                                            // !setcolor COLORNAME
                                            case '!color':
                                                // Changing the global var!
                                                $colorName = $msgparts[0];
                                                // Either get the color by name, or return default black.
                                                if (isset($colors[$colorName])) {
                                                    $color = $colors[$colorName];
                                                } else {
                                                    $color = $colors['black'];
                                                    $colorName = 'black';
                                                }
                                                error_log('Set color: ' . $colorName);
                                                queueSend("privmsg", [textToDec('/msg ' . $nickname . ' Bot color is now *' . $colorName . '*.') , "$color,en"]);
                                                break;

                                            case '!close':
                                                // Try to msg back. Probably wont work.
                                                error_log('-- Bot privately shutting down...');
                                                queueSend("privmsg", [textToDec('/msg ' . $nickname . ' Bot shutting down...') , "$color,en"]);
                                                $running = false;
                                                break;
                                            
                                            case '!username':
                                                if (isset($msgparts[0])) {
                                                    $name = $msgparts[0];
                                                    $id = array_search($msgparts[0], $userlist);
                                                    
                                                    if ($id !== false) {
                                                        $towhom = 'n' . $id . '-' . $name;
                                                        queueSend("privmsg", [textToDec('/userinfo $request'), "#0,en", $towhom]);
                                                    } else {
                                                        queueSend("privmsg", [textToDec('/msg ' . $nickname . ' Nickname not found, sorry.') , "$color,en"]);
                                                    }
                                                } else {
                                                    queueSend("privmsg", [textToDec('/msg ' . $nickname . ' Please provide a nickname to check...') , "$color,en"]);
                                                }
                                                break;

                                            case '!ban':
                                                if (isset($msgparts[0])) {
                                                    $id = array_search($msgparts[0], $userlist);
                                                    if ($id !== false) {
                                                        if ($hasmod[$id] == 1) {
                                                            queueSend("privmsg", [textToDec('/msg ' . $nickname . ' I cannot ban the mod *' . $msgparts[0] . '*.') , "$color,en"]);
                                                            return;
                                                        }
                                                        queueSend("kick", [$msgparts[0], $id]);
                                                        queueSend("privmsg", [textToDec('/msg ' . $nickname . ' Banned user *' . $msgparts[0] . '*.') , "$color,en"]);
                                                        error_log('-- Banned ' . $msgparts[0] . ' in private!');
                                                    } else {
                                                        queueSend("privmsg", [textToDec('/msg ' . $nickname . ' User *' . $msgparts[0] . '* not found!') , "$color,en"]);
                                                    }
                                                } else {
                                                    queueSend("privmsg", [textToDec('/msg ' . $nickname . ' Please provide a nickname to ban...') , "$color,en"]);
                                                }
                                                break;

                                            case '!botters':
                                                queueSend("privmsg", [textToDec('/msg ' . $nickname . ' Botters are: ' . implode(", ", $tempmods)) , "$color,en"]);
                                                break;
                                            
                                            case "!yt":
                                                if (!isset($msgparts[0])) {
                                                    queueSend("privmsg", [textToDec('/msg ' . $nickname . "Give me a Youtube link...") , "$color,en"]);
                                                    return;
                                                }
                                                
                                                $target = $msgparts[0];
                                            
                                                // https://www.youtube.com/watch?v=598zfdASowY
                                                $pos = strpos($target, 'v=');
                                                $v = '';
                                                
                                                if ($pos !== false) {
                                                    $v = substr($target, $pos+2);
                                                } else {
                                                    //  http://youtu.be/598zfdASowY
                                                    $pos = strpos($target, 'youtu.be/');
                                                    
                                                    if ($pos !== false) {
                                                        $v = substr($target, $pos + strlen('youtu.be/'));
                                                    } else {
                                                        queueSend("privmsg", [textToDec('/msg ' . $nickname . "Give me a Youtube link...") , "$color,en"]);
                                                        return;
                                                    }
                                                }
                                                
                                                // Ignore more words.
                                                $p = strpos($v, ' ');
                                                
                                                if ($p !== false) {
                                                    $v = substr($v, 0, $p);
                                                }
                                                
                                                $v = trim($v);
                                                
                                                // Make sure v isnt empty.
                                                if ($v == '') {
                                                    queueSend("privmsg", [textToDec('/msg ' . $nickname . "Give me a Youtube link...") , "$color,en"]);
                                                    return;
                                                }
                                                
                                                queueSend("privmsg", [textToDec("/mbs youTube " . $v . " 0") , "$color,en"]);
                                                break;
                                            
                                            /*
                                            case "!camspam":
                                                for ($i = 0; $i < 10; $i++) {
                                                    queueSend("publish", [$own_user_id, "live"]);
                                                }
                                                break;
                                            */
                                        }
                                    }
                                }
                                break;

                            case '/mbs':
                                error_log('/mbs ' . $arguments);
                                break;
                            
                            case '/mbc':
                                error_log('/mbc ' . $arguments);
                                break;
                            
                            case '/userinfo':
                                $user = isset($parts[0]) ? $parts[0] : false;
                                
                                if ($user !== false) {
                                    if ($user == '$request') {
                                        // Someone's checkin the bot's profile out.
                                        queuesend("privmsg", [textToDec('/msg ' . $nickname . ' Hi there.'), "$color,en"]);
                                    } else {
                                        // Recieving a user's account name, or lack of.
                                        if ($user == '$noinfo') {
                                            // Not logged in.
                                        } else {
                                            $useraccts[$nickname] = $user;  // Create or update.
                                            error_log('-- ' . $nickname . ' is logged in as ' . $user);
                                        }
                                    }
                                }
                                break;

                            default:
                                error_log('-- Uncaught / cmd: ' . $command . ' - ' . $arguments);
                                print_r($payload);
                        }
                    
                        return;
                    }
                    
                    // Ban tinychat room spammers, except mods and tempmods.
                    if ($bothasmod && !$curhasmod && !$curhastempmod) {
                        // Spammers use this newline char a lot, eh.
                        // NOT WORKING I THINK
                        // or this: strpos($payload[1], "133,133") !== false
                        if (strpos($payload[1], "13,10,13,10") !== false) {
                            queueSend("kick", [$nickname, $id]);
                            return;
                        }
                        
                        // Snapshot spammers. 2 Snaps is fine, 3 is spam!
                        if (strpos($message, "I just took a video snapshot of this chatroom.") !== false) {
                            $found = isset($suspectspammer['snapspammer'][$nickname]);
                            if ($found) {
                                $t = time();
                                
                                // Reset user, if it's been a while since last offense.
                                if ($t - $suspectspammer['snapspammer'][$nickname]['time'] > 30) {
                                    $suspectspammer['snapspammer'][$nickname]['time'] = $t;
                                    $suspectspammer['snapspammer'][$nickname]['offense'] = 1;
                                    return;
                                }
                                if ($suspectspammer['snapspammer'][$nickname] == 2) {
                                    queueSend("kick", [$nickname, $id]);
                                    error_log("-- Banned $nickname for spamming!");
                                    unset($suspectspammer['snapspammer'][$nickname]);
                                } elseif ($suspectspammer['snapspammer'][$nickname] == 1) {
                                    $suspectspammer['snapspammer'][$nickname]['offense'] = 2;
                                    queueSend("privmsg", [textToDec("$nickname, do not spam!") , "$color,en"]);
                                }
                            } else {
                                $suspectspammer['snapspammer'][$nickname] = array(
                                    'offense' => 1,
                                    'time' => $t
                                );
                            }
                            return;
                        }
                        
                        // No tinychat room spam.
                        if (preg_match("/tinychat.com\/\w+($| |\/)($| )/i", $message) == 1) {
                            // Ban if already suspect,
                            // otherwise add to suspects array.
                            $t = time();
                            $limit = 60 * 3; // In seconds. User banned, if they repeat during this period.
                            
                            $found = isset($suspectspammer['linkspammer'][$nickname]);
                            if ($found) {
                                if ($t - $suspectspammer['linkspammer'][$nickname] <= $limit) {
                                    $id = array_search($nickname, $userlist);
                                    queueSend("kick", [$nickname, $id]);
                                    error_log("-- Banned $nickname ($id) for spamming!");
                                    unset($suspectspammer['linkspammer'][$nickname]);
                                    return;
                                } else {
                                    $suspectspammer['linkspammer'][$nickname] = $t; // Update time of offense.
                                    queueSend("privmsg", [textToDec("$nickname, do not spam!") , "$color,en"]);
                                }
                            } else {
                                $suspectspammer['linkspammer'][$nickname] = $t;
                                error_log("-- Added $nickname to spammers list!");
                                queueSend("privmsg", [textToDec("$nickname, do not spam!") , "$color,en"]);
                                return;
                            }
                        }
                        
                        // For banned words.
                        $length = count($bannedwords);
                        if ($length != 0) {
                            foreach($bannedwords as $word) {
                                $found = strpos($message, $word);
                                if ($found !== false) {
                                    $id = array_search($nickname, $userlist);
                                    queueSend("kick", [$nickname, $id]);
                                    queueSend("privmsg", [textToDec("Banned $nickname for using a bad word!") , "$color,en"]);
                                    error_log("-- Banned $nickname for using a banned word!");
                                    return;
                                }
                            }
                        }
                    }
                    
                    // just look at default when packets come in and it will show the structure
                    // of how the packet is formed
                    // that's how you know what index of the array to grab bits of
                    // data from, like to = 0, message = 1 etc
                    // all privmsg (messages) in tinychat are encoded in decimal
                    // deliminated by commas , so that's what decToText does
                    // if you want to send a message you need to
                    // encode it in decimal with textToDec("string message")
                    // and send that and not the raw message
                    // special characters aren't working well with this you'll
                    // have to figure it out yourself if you care about them
                    // that badly.
                    
                    if ($message[0] == "!") {
                        $parts = explode(" ", $message);
                        $command = array_shift($parts);
                        $text = implode(" ", $parts);
                        // $color = "$color,en";
                        
                        if (isset($parts[0])) {
                            $target = $parts[0]; // Use in TC like: /CMD TARGET
                        }
                        
                        switch ($command) {
                            // Toggles greeting new nicks.
                            case "!greet":
                                // User must be mod.
                                if (!$curhasmod) return;
                                
                                $GreetUsers = !$GreetUsers;
                                
                                $msg = 'Greets are now *' . ($GreetUsers ? 'on' : 'off') . '*.';
                                
                                queueSend("privmsg", [textToDec($msg) , "$color,en"]);
                                break;
                            
                            case "!forgive":
                                // Bot must be mod, and user mod.
                                if (!($bothasmod && $curhasmod)) return;
                                
                                if (isset($target)) {
                                    $id = isset($bannedusers[$target]) ? $bannedusers[$target] : false;
                                    
                                    if ($id !== false) {
                                        // tempmods can only raise an alert, for a mod to forgive the person.
                                        if ($curhastempmod && !$curhasmod) {
                                            queueSend("privmsg", [textToDec("Should *" . $target . "* be forgiven??") , "$color,en"]);
                                            return;
                                        }
                                        
                                        error_log("Forgiving " . $target . " (" . $id . ")");
                                        queueSend("forgive", [$id]);
                                        queueSend("privmsg", [textToDec("*" . $target . "* is now forgiven.") , "$color,en"]);
                                    } else {
                                        queueSend("privmsg", [textToDec("*" . $target . "* is not listed in the banned list!") , "$color,en"]);
                                    }
                                } else {
                                    $bannedmsg = 'No users are on my banned list.';
                                    
                                    if (count($bannedusers) > 0) {
                                        $bannedmsg = "These users were banned: *" . implode(', ', array_keys($bannedusers) . "*.");
                                    }
                                    
                                    queueSend("privmsg", [textToDec($bannedmsg), "$color,en"]);
                                }
                                break;
                            
                           /* case "!banthese":
                                // Bot must be mod, and user mod.
                                if (!($bothasmod && $curhasmod)) return;
                                
                                if (!isset($target)) {
                                    queueSend("privmsg", [textToDec("You must give me a part of a name...") , "$color,en"]);
                                    return;
                                }
                                
                                $count = 0; // To avoid freezing the bot for too long.
                                foreach($userlist as $key => $value) {
                                    $count = $count + 1;
                                    if ($count > 10) break;
                                    // Remove nicknames that start with 'guest'.
                                    if (strpos($value, $target) !== false) {
                                        if ($hasmod[$key] == 1) continue; // skip mods
                                        queueSend("kick", [$value, $key]);
                                    }
                                }
                                
                                queueSend("privmsg", [textToDec("Finished banning *$count* of *$target*.") , "$color,en"]);
                                break;*/
                            
                            // List the autobanlist.
                            case "!autobanlist":
                                if (!$bothasmod) return;
                                
                                if (!empty($autoban['list'])) {
                                    queueSend("privmsg", [textToDec('The following nicknames are on autoban: ' . implode(', ', $autoban['list'])) , "$color,en"]);
                                } else {
                                    queueSend("privmsg", [textToDec("Banlist is empty.") , "$color,en"]);
                                }
                                break;
                            
                            // Protect the room! All new-joiners are banned immediately.
                            // Or, toggle nickname in autobanlist.
                            case "!autoban":
                                // Bot must be mod, and user mod.
                                if (!($bothasmod && $curhasmod)) return;
                                
                                if (isset($target)) {
                                    $key = array_search($target, $autoban['list'], true);
                                    if ($key === false) {
                                        array_push($autoban['list'], $target);
                                        queueSend("privmsg", [textToDec("*$target* has been added to the autoban list!") , "$color,en"]);
                                    } else {
                                        unset($autoban['list'][$key]);
                                        queueSend("privmsg", [textToDec("$target is no longer in the autoban list.") , "$color,en"]);
                                    }
                                } else {
                                    if (!$autoban['mode']) {
                                        // make active
                                        $autoban['mode'] = true;
                                        $safeusers['list'] = $userlist; // Assigned by copy, not reference.
                                        queueSend("privmsg", [textToDec("*WARNING* Autoban mode is now on!") , "$color,en"]);
                                        // How long to wait, until next autoban against safelist.
                                        $safeusers['time'] = time(); // In seconds.
                                    } else {
                                        // make inactive
                                        $safeusers['time'] = 0;
                                        $autoban['mode'] = false;
                                        $safeusers['list'] = [];
                                        queueSend("privmsg", [textToDec("*NOTICE* Autoban mode is now off.") , "$color,en"]);
                                    }
                                }
                                break;

                            case "!android":
                                // Bot must be mod, and user mod.
                                if (!($bothasmod && $curhasmod)) return;
                                
                                if (!$androidban) {
                                    // make active
                                    $androidban = true;
                                    queueSend("privmsg", [textToDec("*Android ban: On*") , "$color,en"]);
                                } else {
                                    // make inactive
                                    $androidban = false;
                                    queueSend("privmsg", [textToDec("*Android ban: off*") , "$color,en"]);
                                }
                                break;
                                
                            // Ban all users lol.
                            case "!nuke":
                                // Bot must be mod, and user mod.
                                if(!($bothasmod && $curhasmod)) return;
                                
                                // make sure this has the admin ACCOUNT name (not nickname!)
                                $admin = 'socremix';
                                
                                // Default only admin can use it.
                                if($useraccts[$nickname] != $admin) return;
                                
                                $killed = [];
                                
                                // print_r($userlist);
                                foreach ($userlist as $key => $value) {
                                    if ($key == $own_user_id) continue; // skip self
                                    
                                    // skip mods - change to FALSE if bot uses the admin account.
                                    if (true) {
                                        if (!empty($key) && isset($hasmod[$key])) {
                                            if ($hasmod[$key] == 1) continue; 
                                        }
                                    }
                                    
                                    $curacct = isset($useraccts[$value]) ? $useraccts[$value] : '';
                                    
                                    if ($curacct == $admin) continue;  // skip admin - irrelevant if skips mods.
                                    
                                    $killed[] = $value;
                                    
                                    // THIS COMMAND SHOULD NEVER ACTUALLY BE USED!
                                    // queueSend("kick", [$value, $key]);
                                }
                                
                                queueSend("privmsg", [textToDec("*TESTMODE* Found targets: *" . implode(', ', $killed) . '*.'), "$color,en"]);
                                
                                queueSend("privmsg", [textToDec("*Finished banning all users.*"), "$color,en"]);
                                break;
                            
                            case '!color':
                                // Requires mod or tempmod permission.
                                if (!$curhastempmod && !$curhasmod) return;
                                
                                if (!isset($target)) {
                                    queueSend("privmsg", [textToDec("Available colors are: " . implode(', ', array_keys($colors))) , "$color,en"]);
                                } else {
                                    if (isset($colors[$target])) {
                                        $color = $colors[$target];
                                        queueSend("privmsg", [textToDec("Default color is now *$target*.") , "$color,en"]);
                                    } elseif ($target[0] == '#' && strlen($target) <= 7 && preg_match('/[^#1234567890abcdef]/i', $target) == 0) {
                                        $color = $colors[$target];
                                        queueSend("privmsg", [textToDec("Default color is now *$target*.") , "$color,en"]);
                                    } else {
                                        queueSend("privmsg", [textToDec("Invalid color *$target*! Either choose a listed color name, or a valid hexadecimal value!") , "$color,en"]);
                                    }
                                }
                                break;
                            
                            // Toggles autoban for anyone who goes on cam.
                            // Good for cambot spammers.
                            case "!camban":
                                // Bot must be mod, and user mod.
                                if (!($bothasmod && $curhasmod)) return;
                                
                                if (!$camban) {
                                    // make active
                                    $camban = true;
                                    queueSend("privmsg", [textToDec("*WARNING* Anyone who goes on cam will be banned!") , "$color,en"]);
                                } else {
                                    // make inactive
                                    $camban = false;
                                    queueSend("privmsg", [textToDec("*NOTICE* You can go on cam now, without being banned.") , "$color,en"]);
                                }
                                break;
                            
                            // Toggles auto-uncam for anyone who goes on cam.
                            // Good for cambot spammers.
                            case "!camclose":
                                // Bot must be mod, and user mod.
                                if (!($bothasmod && $curhasmod)) return;
                                
                                if (!$camban) {
                                    // make active
                                    $camban = true;
                                    queueSend("privmsg", [textToDec("*NOTICE* Any user who goes on cam will be uncammed automatically.") , "$color,en"]);
                                } else {
                                    // make inactive
                                    $camban = false;
                                    queueSend("privmsg", [textToDec("*NOTICE* You can go on cam now, without being closed.") , "$color,en"]);
                                }
                                break;
                            
                            // Make the user of a word, not case-sensitive,
                            // including sub-strings, get a ban.
                            case "!banword":
                                // Requires mod or tempmod permission.
                                if (!$curhastempmod && !$curhasmod) return;
                                
                                if (isset($target)) {
                                    $key = array_search($target, $bannedwords);
                                    if ($key === false) {
                                        array_push($bannedwords, $target);
                                        queueSend("privmsg", [textToDec("*$target* is now a banned word.") , "$color,en"]);
                                    } else {
                                        unset($bannedwords[$key]); // toggle off.
                                        queueSend("privmsg", [textToDec("Removed *$target* from the banned words list.") , "$color,en"]);
                                    }
                                } else {
                                    if (count($bannedwords) == 0) {
                                        queueSend("privmsg", [textToDec("No words have been banned!") , "$color,en"]);
                                    } else {
                                        queueSend("privmsg", [textToDec("Banned words: " . implode(", ", $bannedwords)) , "$color,en"]);
                                    }
                                }
                                break;

                            case "!rename":
                                // Requires mod or tempmod permission.
                                if (!$curhastempmod && !$curhasmod) return;
                                
                                if (isset($target)) {
                                    $legal = preg_match("/[^a-zA-Z0-9\-{}]+/", $target);
                                    
                                    if ($legal !== 0) {
                                        queueSend("privmsg", [textToDec("No special characters, please...") , "$color,en"]);
                                        return;
                                    }
                                    
                                    if (trim($target) == "") {
                                        queueSend("privmsg", [textToDec("I want an actual name, please...") , "$color,en"]);
                                        return;
                                    }
                                    
                                    queueSend("nick", [$target]); // Only alphanumeric.
                                    
                                    $botname = $target; // Update local variable, so bot identifies itself.
                                } else {
                                    queueSend("nick", ["PurpleBot"]);
                                }
                                break;
                            
                            // toggle on/off bot control for nickname.
                            case "!botter":
                                // Requires mod or tempmod permission.
                                if (!$curhastempmod && !$curhasmod) return;
                                
                                if (isset($target)) {
                                    error_log("Toggling " . $target . "'s bot control.");
                                    $key = array_search($target, $tempmods);
                                    
                                    if ($key === false) {
                                        array_push($tempmods, $target);
                                        // error_log("Users with bot control: " . implode(", ", $tempmods));
                                        queueSend("privmsg", [textToDec("*$target* added to bot control!") , "$color,en"]);
                                    } else {
                                        unset($tempmods[$key]);
                                        queueSend("privmsg", [textToDec("*$target* removed from bot control.") , "$color,en"]);
                                    }
                                }
                                break;

                            case "!ban":
                                // Bot must be mod, and user either tempmod or mod.
                                if (!($bothasmod && ($curhastempmod || $curhasmod))) return;
                                
                                if (isset($target)) {
                                    $id = array_search($target, $userlist);
                                    
                                    if ($id !== false) {
                                        if ($hasmod[$id] == 1) {
                                            queueSend("privmsg", [textToDec("I can't ban the mod *" . $target . "*.") , "$color,en"]);
                                            return;
                                        }
                                        
                                        // tempmods can only raise an alert, for a mod to ban the person.
                                        if ($curhastempmod && !$curhasmod) {
                                            queueSend("privmsg", [textToDec("Should *" . $target . "* be banned??") , "$color,en"]);
                                            return;
                                        }
                                        
                                        error_log("Banning " . $target . " (" . $id . ")");
                                        queueSend("kick", [$target, $id]);
                                    } else {
                                        queueSend("privmsg", [textToDec("Person not found!") , "$color,en"]);
                                    }
                                } else {
                                    queueSend("privmsg", [textToDec("You must tell me a name...") , "$color,en"]);
                                }
                                break;

                            case "!pause":
                                // Bot must be mod, and user either tempmod or mod.
                                if (!($bothasmod && ($curhastempmod || $curhasmod))) return;
                                
                                queueSend("privmsg", [textToDec("/mbpa youTube") , "$color,en"]);
                                break;

                            case "!play":
                                // Bot must be mod, and user either tempmod or mod.
                                if (!($bothasmod && ($curhastempmod || $curhasmod))) return;
                                
                                if (isset($target)) {
                                    $timesecs = 0; // tinychat needs milliseconds.
                                    
                                    if (!preg_match('/[^\d+]/i', $target)) {
                                        $timesecs = (int)$target * 1000;
                                    } else {
                                        $timeparts = explode('m', $target);
                                        $timeparts[0] = isset($timeparts[0]) ? (int)$timeparts[0] : 0;
                                        $timeparts[1] = isset($timeparts[1]) ? (int)$timeparts[1] : 0;
                                        
                                        if ($timeparts !== false && !empty($timeparts)) {
                                            if ($timeparts[0] < 1000 && $timeparts[1] < 60) {
                                                // Looks like 12m35 and so on.
                                                $timesecs = (($timeparts[0] * 60) + $timeparts[1]) * 1000;
                                            }
                                        }
                                    }
                                    queueSend("privmsg", [textToDec("/mbpl youTube $timesecs") , "$color,en"]);
                                } else {
                                    queueSend("privmsg", [textToDec("/mbpl youTube 0") , "$color,en"]);
                                }
                                break;

                            case "!close":
                                // Bot must be mod, and user either tempmod or mod.
                                if (!($bothasmod && ($curhastempmod || $curhasmod))) return;
                                
                                // So people don't confuse this with !uncam USER
                                if (isset($target)) {
                                    queueSend("privmsg", [textToDec("Did you mean to use the *!uncam* command?") , "$color,en"]);
                                    return;  
                                }
                                
                                queueSend("privmsg", [textToDec("/mbc youTube") , "$color,en"]);
                                break;

                            case "!skip":
                                // Bot must be mod, and user either tempmod or mod.
                                if (!($bothasmod && ($curhastempmod || $curhasmod))) return;
                                
                                if (isset($target)) {
                                    $timesecs = 0; // tinychat needs milliseconds.
                                    
                                    if (!preg_match('/[^\d+]/i', $target)) {
                                        $timesecs = (int)$target * 1000;
                                    } else {
                                        $timeparts = explode('m', $target);
                                        
                                        if (!isset($timeparts[0]) || empty($timeparts[0])) {
                                            $timeparts[0] = 0;
                                        }
                                        
                                        if (!isset($timeparts[1]) || empty($timeparts[1])) {
                                            $timeparts[1] = 0;
                                        }
                                        
                                        if ($timeparts !== false && !empty($timeparts)) {
                                            // Looks like 12m35 and so on.
                                            $timesecs = (($timeparts[0] * 60) + $timeparts[1]) * 1000;
                                        }
                                    }
                                    
                                    queueSend("privmsg", [textToDec("/mbsk youTube $timesecs") , "$color,en"]);
                                } else {
                                    queueSend("privmsg", [textToDec("/mbsk youTube 0") , "$color,en"]);
                                }
                                break;

                            case "!sc":
                                // Bot must be mod, and user either tempmod or mod.
                                if (!($bothasmod && ($curhastempmod || $curhasmod))) return;
                                
                                if (isset($target)) {
                                    queueSend("privmsg", [textToDec("/mbs soundCloud $target") , "$color,en"]);
                                } else {
                                    queueSend("privmsg", [textToDec("I need a song id...") , "$color,en"]);
                                }
                                break;

                            case "!scplay":
                                // Bot must be mod, and user either tempmod or mod.
                                if (!($bothasmod && ($curhastempmod || $curhasmod))) return;
                                
                                // target for soundcloud is originally something like this: 10286.439909297053
                                if (isset($target)) {
                                    queueSend("privmsg", [textToDec("/mbpl soundCloud $target") , "$color,en"]);
                                } else {
                                    queueSend("privmsg", [textToDec("/mbpl soundCloud 0") , "$color,en"]);
                                }
                                break;

                            case "!scpause":
                                // Bot must be mod, and user either tempmod or mod.
                                if (!($bothasmod && ($curhastempmod || $curhasmod))) return;
                                
                                queueSend("privmsg", [textToDec("/mbpa soundCloud") , "$color,en"]);
                                break;

                            case "!scclose":
                                // Bot must be mod, and user either tempmod or mod.
                                if (!($bothasmod && ($curhastempmod || $curhasmod))) return;
                                
                                queueSend("privmsg", [textToDec("/mbc soundCloud") , "$color,en"]);
                                break;

                            case "!uncam":
                                // Bot must be mod, and user mod.
                                if (!($bothasmod && ($curhastempmod || $curhasmod))) return;
                                
                                if (isset($target)) {
                                    queueSend("owner_run", ["_close$target"]);
                                } else {
                                    queueSend("privmsg", [textToDec("Tell me who to close...") , "$color,en"]);
                                }
                                break;

                            case "!banguests":
                                // Bot must be mod, and user either tempmod or mod.
                                if (!($bothasmod && ($curhastempmod || $curhasmod))) return;
                                
                                $count = 0;
                                foreach($userlist as $key => $value) {
                                    $count = $count + 1;
                                    if ($count > 10) break;  // To avoid freezing the bot for too long.
                                    
                                    // Remove nicknames that start with 'guest'.
                                    if (strpos($value, 'guest-') !== false) {
                                        if ($hasmod[$key] == 1) continue; // Skip mods.
                                        
                                        queueSend("kick", [$value, $key]);
                                    }
                                }
                                break;

                            case "!say":
                                if (trim($text) == '') return;
                                
                                queueSend("privmsg", [textToDec($text) , "$color,en"]);
                                break;
							//close all will probably be a problem more then anything so lets just disable it
                            /*case "!closeall":
                                $user_id = array_search($nickname, $userlist);
                                
                                if ($hasmod[$user_id] == 1) {
                                    $nickname = $parts[0];
                                    foreach($userlist as $user_id => $nickname) {
                                        if (strtolower($nickname) == strtolower($nickname)) {
                                            queueSend("owner_run", ["_close$nickname"]);
                                        }
                                    }
                                }
                                break;*/

                            case "!loser":
                                $loserarray = array(
                                    "samuel",
                                    " 0d1n",
                                    "jt...fucking loser",
                                    "kidose",
                                    "badgerboob",
                                    "therosenberg",
                                    "nigra",
                                    "fedora",
                                    "shoop",
                                    "mo",
                                    "wally3",
                                    "katie4c",
                                    "tas",
                                    "herse",
                                    "basebsd",
                                    "nightzz",
                                    "everyone even *$nickname*",
                                    "samuel...cuz he sucks cock",
                                    "hitler"
                                );
                                $random = rand(0, count($loserarray) - 1);
                                queueSend("privmsg", [textToDec(" $loserarray[$random]") , "$color,en"]);
                                break;

                            case "!rate":
                                $ratearray = array(
                                    "nerd/10",
                                    " do they even shower?",
                                    "pale as fuck/10",
                                    "no tits/10 ",
                                    "STFU *$nickname* YOu are a nigger",
                                    "my penis is growing",
                                    "muscles/10",
                                    "I got aids bitch!",
                                    " terrible aesthetics ...just terrible",
                                    " would fuck for sure...but im a bot...",
                                    "8.3/10",
                                    " ewwww...not worthy of my rate ",
                                    "*$nickname* wants my cock",
                                    "Would impregnate /10",
                                    "needs more tits, faggot",
                                    "I just shat myself....",
                                    "make over needed!",
                                    " goyim/10",
                                    "that a nigger? if so, -1/10",
                                    "0/10",
                                    " 1/10",
                                    "*2/10",
                                    "3/10 ",
                                    "4/10",
                                    "5/10",
                                    "6/10",
                                    "7/10",
                                    "let me think about it",
                                    "why the long face?",
                                    "metrosexual/10ts gonna be",
                                    "Go away, im trying to sleep!",
                                    "shitbag/10",
                                    "meth head for sure",
                                    "0.00001/10",
                                    "flat out ugly!",
                                    "Downright disgusting!",
                                    "no",
                                    "5.663/10",
                                    "pedophile/10",
                                    "simply...beautiful",
                                    "classy/10",
                                    "No! I don't judge people, fag",
                                    "nicest breast ever/10",
                                    "whore/10",
                                    "10/10 ...perfect pretty much",
                                    "fuck of *$nickname* how about I rate you instead?",
                                    "I like big butts, and I cannot lie, wait maybe I can...",
                                    "HOT FUCKING MANGINA! would smash.",
                                    "mehh....let's say 0.2/10",
                                    "jew/10",
                                    "something that ugly, should not exist, *$nickname*"
                                );
                                $random = rand(0, count($ratearray) - 1);
                                queueSend("privmsg", [textToDec(" $ratearray[$random]") , "$color,en"]);
                                break;

                            case "!flip":
                                $fliparray = array(
                                    " *tails*",
                                    " *heads*"
                                );
                                $random = rand(0, count($fliparray) - 1);
                                queueSend("privmsg", [textToDec(" $fliparray[$random]") , "$color,en"]);
                                break;

                            case "!ethnicity":
                                $etcarray = array(
                                    "You are now a nigger....how unforunate ",
                                    "You sir are a upper class middle adged balding white man",
                                    "You are now a spic",
                                    "You are now a chink"
                                );
                                $random = rand(0, count($etcarray) - 1);
                                queueSend("privmsg", [textToDec(" $etcarray[$random]") , "$color,en"]);
                                break;

                            case "!td":
                                $tdarray = array(
                                    "Truth",
                                    "Dare",
                                    "You decide",
                                    "Roll again"
                                );
                                $random = rand(0, count($tdarray) - 1);
                                queueSend("privmsg", [textToDec(" $tdarray[$random]") , "$color,en"]);
                                break;

                            case "!privmsg";
                                if ($hasmod[$user_id] == 1) {
                                    $nickname = $parts[0];
                                    $user_id = array_search($nickname, $userlist);
                                    queueSend("privmsg", [textToDec("/msg $nickname *Hey there how's it goin.*") , "$color,en"]);
                                }
                                break;
																
                            
                          /*  case "!drug":
                                $drugarray = array(
                                    "*$botname* gives you LSD...enjoy",
                                    " *$botname* gives you speed...wooooo!",
                                    "*$botname* gives you a fist to the face, didn't expect that did ya?",
                                    "*$botname* gives you Adderal ",
                                    " *$botname* gives you 2mg Xanax Bars ",
                                    "*$botname* gives you modafanil",
                                    "*$botname* gives you a shot of adrenaline ....Go!",
                                    "*$botname* gives you MDMA",
                                    " *$botname* gives you Marijuana ",
                                    "*$botname* gives you PCP",
                                    "*$botname* gives you Black Tar Heroin",
                                    "*$botname* gives you Cocaine",
                                    "*$botname* gives you 2c-b",
                                    "*$botname* says sorry we're out of drugs addict!",
                                    "*$botname gives you Meth",
                                    "*$botname* gives you PCP",
                                    "*$botname* gives you Vitmain B-12",
                                    "*$botname* gives you Vitamin C",
                                    "*$botname* gives you a pack of skittles and Arizona Iced Tea",
                                    "*$botname* hands you a pack of gum instead",
                                    "*$botname* gives you Jenkem",
                                    "*$botname* gives you DMT",
                                    "*$botname* gives you mescaline",
                                    "*$botname* hands you a joint, smoke up bitch!*"
                                );
                                $random = rand(0, count($drugarray) - 1);
                                queueSend("privmsg", [textToDec(" $drugarray[$random]") , "$color,en"]);
                                break; */

                            case "!yt":
                                if (!($bothasmod && ($curhastempmod || $curhasmod))) {
                                    queueSend("privmsg", [textToDec("Not for you.") , "$color,en"]);
                                    return;
                                }
                                
                                if (!isset($target)) {
                                    queueSend("privmsg", [textToDec('/msg ' . $nickname . "Give me a Youtube link...") , "$color,en"]);
                                    return;
                                }
                                
                                // https://www.youtube.com/watch?v=598zfdASowY
                                $pos = strpos($target, 'v=');
                                $v = '';
                                
                                if ($pos !== false) {
                                    $v = substr($target, $pos+2);
                                } else {
                                    //  http://youtu.be/598zfdASowY
                                    $pos = strpos($target, 'youtu.be/');
                                    
                                    if ($pos !== false) {
                                        $v = substr($target, $pos + strlen('youtu.be/'));
                                    } else {
                                        queueSend("privmsg", [textToDec("Give me a Youtube link...") , "$color,en"]);
                                        return;
                                    }
                                }
                                
                                // Ignore more words.
                                $p = strpos($v, ' ');
                                
                                if ($p !== false) {
                                    $v = substr($v, 0, $p);
                                }
                                
                                $v = trim($v);
                                
                                // Make sure v isnt empty.
                                if ($v == '') {
                                    queueSend("privmsg", [textToDec("Give me a Youtube link...") , "$color,en"]);
                                    return;
                                }
                                
                                queueSend("privmsg", [textToDec("/mbs youTube " . $v . " 0") , "$color,en"]);
                                break;
								
							case "!reaper":
                                    queueSend("privmsg", [textToDec("/mbs youTube ClQcUyhoxTg 0") , "$color,en"]);
                                    break;
									
                            case "!ban":
                                queueSend("privmsg", [textToDec("*$botname* banning all Guests...Complete") , "$color,en"]);
                                break;

                            case "!sfw":
                                queueSend("privmsg", [textToDec("http://tinychat.com/embed/Tinychat-11.1-1.0.0.0578.swf?version=1.0.0.0578&target=client&key=tinychat&room=socremix") , "$color,en"]);
                                break;
                        }
                    }
                    break;
                       
                case "joins":
                        error_log('-- joins event.');
                        $length = count($payload) - 1;
                        
                        for($i = 3; $i < $length; $i++){
                                $user_id = $payload[$i++];
                                $nickname = $payload[$i];
                                if (!empty($user_id)) $userlist[$user_id] = $nickname;
                                //$acclist[$user_id] = 0;
                                //$trivia[$user_id] = 0;
                                //$points[$user_id] = 0;
                                if (!empty($user_id)) $hasmod[$user_id] = 0;
                        }
                        break;
                        
                //this is the nick packet
                case "nick":
                    $guest_id = $payload[0];
                    $nickname = $payload[1];
                    $user_id = $payload[2];
                    
                    // Only people who make some nickname are allowed.
                   // if (strpos($nickname, 'newuser') !== false){
                     //   queueSend("kick", [$nickname , $user_id]);
                      //  return;
                    //}
                        if (strpos($nickname, 'samejeff') !== false){
                         queueSend("privmsg", [textToDec("OWNED") , "$color,en"]);
						queueSend("kick", [$nickname , $user_id]);
                        return;
                    }
					     
                    // Autoban from banlist.
                    foreach ($autoban['list'] as $id => $name) {
                        if (strpos($nickname, $name) !== false) {
                            queueSend("kick", [$nickname , $user_id]);
                            return;
                        }
                    }
                    
                    // Update $userlist when user changes nickname.
                    if (!empty($user_id)) $userlist[$user_id] = $nickname;
                    //error_log("User " . $guest_id . " changed nick to " . $nickname);
                    
                    if ($nickname != $botname) {
                        if ($GreetUsers) {
						//
                            //$GreetingMessages = array( "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""); 
                            $random = rand(0, count($GreetingMessages) - 1);
                            queueSend("privmsg", [ textToDec("$GreetingMessages[$random]"), "$color,en" ]);
                        }
                        
                        // get account name.
                        $towhom = 'n' . $user_id . '-' . $nickname;
                        queueSend("privmsg", [textToDec('/userinfo $request'), "#0,en", $towhom]);
                    }
                    break;
                /*
                case "nick";
                        $guest_id = $payload[0];
                        $nickname = $payload[1];
                        $user_id = $payload[2];
                        queueSend("privmsg", [ textToDec('/userinfo $request'), '$color,en'  , $nickname , $userlist[$user_id]]);
                        //if ($nickname!=$botname)
                        //queueSend("privmsg", [ textToDec(" punches *$nickname* in face"), '$color,en']);
                        break;
                */
                
                //this is the oper packet
                case "oper":
                        $user_id = $payload[0];
                        $nickname = $payload[1];
                        
                        // TC is retarded. payload is lots of keys sometimes, so need to sift them.
                        if (empty($user_id)) {
                            foreach ($payload as $key => $value) {
                                if (!empty($value)) {
                                    $nickname = $value;
                                    $user_id = substr($value, 6);  // remove the guest- part, to get id.
                                }
                            }
                        }
                        
                        $hasmod[$user_id] = 1;
                        
                        // If bot is mod.
                        if ($user_id == $own_user_id) {
                            $bothasmod = true;
                        }
                        
                        // print_r($payload); // remove
                        
                        error_log($nickname . ' is oper.');
                        
                        // get account name...
                        $towhom = 'n' . $user_id . '-' . $nickname;
                        queueSend("privmsg", [textToDec('/userinfo $request'), "#0,en", $towhom]);
                        // one of these should work.
                        $towhom = 'b' . $user_id . '-' . $nickname;
                        queueSend("privmsg", [textToDec('/userinfo $request'), "#0,en", $towhom]);
                        break;
                       
                // each join
                case "join":
                        $user_id = $payload[0];
                        $nickname = $payload[1];
                        $hasmod[$user_id] = 0;
                        
                        $time = time();
                        
                        //$autoban time count mode list
                        if ($bothasmod) {
                            if ($autoban['mode']) {
                                $time = time();
                                $maxtime = 20;
                                $maxjoins = 5;
                                
                                if ($autoban['time'] == 0) {
                                    // Start counting and timing.
                                    $autoban['time'] = $time;
                                    $autoban['count'] = 1;
                                    
                                    queueSend("kick", [$nickname, $user_id]);
                                    return;
                                } elseif ($time - $autoban['time'] > $maxtime && $autoban['count'] < $maxjoins) {
                                    // Not so many joins, so stop autoban.
                                    $autoban['mode'] = false;
                                    $autoban['time'] = 0;
                                    
                                    queueSend("privmsg", [textToDec("*Autoban [automatically]: OFF*"), "$color,en"]);
                                } elseif ($time - $autoban['time'] > $maxtime) {
                                    // Continue autoban, and reset timer, to repeat.
                                    $autoban['time'] = $time;
                                    $autoban['count'] = 1;
                                    
                                    queueSend("kick", [$nickname, $user_id]);
                                    return;
                                } else {
                                    // Continue counting joins.
                                    $autoban['count'] = $autoban['count'] + 1;
                                    
                                    queueSend("kick", [$nickname, $user_id]);
                                    return;
                                }
                            }
                            
                            if ($androidban) {
                                if (strpos(":android", $user_id) !== false) {
                                    queueSend("privmsg", [textToDec("No Android users allowed."), "$color,en"]);
                                    queueSend("kick", [$nickname, $user_id]);
                                    return;
                                }
                            }
                            
                            $maxtime = 20;
                            $maxjoins = 20;
                            
                            // Activate autoban if too many joins too quickly.
                            if ($joins['time'] == 0) {
                                // Start counting and timing.
                                $joins['time'] = time();
                                $joins['count'] = $joins['count'] + 1;
                            } elseif ($time - $joins['time'] < $maxtime && $joins['count'] > $maxjoins) {
                                // Too many joins, in too short a time!
                                $autoban['mode'] = true;
                                
                                queueSend("privmsg", [textToDec("*Autoban [automatically]: ON*"), "$color,en"]);
                            } elseif ($time - $joins['time'] > $maxtime) {
                                $joins['count'] = 0;
                                $joins['time'] = 0;
                            } else {
                                $joins['count'] = $joins['count'] + 1;
                            }
                        }
                        
                        //queueSend("owner_run", [textToDec("_noticeWelcome $nickname to the room!"), "$color,en" ]);
                       
                        // check to see if we have got our user_id yet
                        // if not then set this user_id as the FIRST person that "joins"
                        // the room (calls this) is yourself so you can rely on this
                        // to get your own user_id
                        if (!$own_user_id) {                           
                                $own_user_id = $user_id;
                                $url = "http://tinychat.com/api/captcha/check.php?room=tinychat^$roomname&guest_id=$own_user_id";
                                $ch = curl_init($url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                $json = json_decode(curl_exec($ch), true);
                                if ($json['ok'] == true) {
                                        $cauth = $json['key'];
                                        queueSend("cauth", [ $cauth ]);
                                }
                        }
 
                        // Don't add to userlist, if no ID. Fuck TC buggy.
                        if (!empty($user_id)) $userlist[$user_id] = $nickname;
                        
                        //error_log($user_id . " is " . $nickname);
 
                        echo "-- $nickname joined the room.\n";
                        break;
                
                // this is called pretty much at the end of the room join processing
                case "joinsdone":
                        queueSend("nick", [ $botname ]);
                        queueSend("pro", [ $prohash ]);
                        
                        error_log('-- joinsdone event.');
                        
                        //print_r($userlist);
                        
                        //error_log("Usercount: " . count($userlist));
                        //queueSend("privmsg", [ textToDec("There are " . count($userlist) . " in the room."), "$color,en" ]);
                         //queueSend("privmsg", [ textToDec("*$botname has been loaded!*"), "$color,en" ]);
                        break;
                
                default:
                        // payload is an array of the info passed
                        error_log("UNCAUGHT EVENT: $event - $arguments");
                        // uncomment this if you want to see data
                        print_r($payload);
                        error_log('');
                        
                        break;
        }
}

 
/**
 * readPayloadString takes a string and pointer to read in one string and returns it and auto increases pointer
 * @param $string String the payload you want to parse
 * @param &$p Integer the "pointer" position in the string you're reading from
 * @return String the string read out
 */
function readPayloadString($string, &$p) {
        // make sure that we're reading in a string
        if (ord($string[$p++]) != 2)
                return null;
        // grab the length of the string
        $length = hexdec(sprintf("%02X", ord($string[$p++])).sprintf("%02X", ord($string[$p++])));;
        // initialize the return string
        $return = "";
        // read through over characters with length and append the string
        for ($i = 0; $i < $length; $i++)
                $return .= substr($string, $p++, 1);
        // return the string back
        return $return;
}
 
/**
 * readPayload takes in the payload string you want to read and returns an array of strings
 * @param $string String the payload you want to parse
 * @return Array the array of strings returned back
 */
function readPayload($string) {
        // initialize our "pointer"
        $p = 0;
        // read in the command
        $command = readPayloadString($string, $p);
        // increase the pointer past 10 bytes
        $p += 10;
        // initialize return array
        $return = array();
        // keep reading in strings until we have met the length
        while ($p < strlen($string))
                $return[] = readPayloadString($string, $p);
        // return the array of strings
        return $return;
}
 
/**
 * decToText takes a decimal comma delmited string and turns it into a normal string
 * @param $string String the decimal comma delimited string
 * @return String the normal string
 */
function decToText($string) {
        // explode the string into decimal characters by the comma
        $dec_array = explode(",", $string);
        // initialize the return string
        $return = "";
        // loop over the decimal characters and append the string with the characters
        foreach ($dec_array as $character)
                $return .= chr($character);
        // return the string
        return $return;
}
 
/**
 * textToDec takes a normal string and turns it into a comma delimited decimal string
 * @param $string String the normal string
 * @return String the decimal string
 */
function textToDec($string) {
        // intitialize the ord array
        $ord_array = array();
        // loop through each character
        for ($i = 0; $i < strlen($string); $i++)
                $ord_array[] = ord($string[$i]);
        // implode with commas into the return string and return it
        return implode(",", $ord_array);
}

//**END**
?>