<?php
// modified version of my user agent script on github
$ua_global = $_SERVER['HTTP_USER_AGENT'];
    
function get_browser_name($user_agent) {
    if (strpos($user_agent, 'Opera') || strpos($user_agent, 'OPR/')) return 'opera';
    elseif (strpos($user_agent, 'Edge') || strpos($user_agent, 'Edg/')) return 'Microsoft Edge';
    elseif (strpos($user_agent, 'Chrome')) return 'Google Chrome';
    elseif (strpos($user_agent, 'Safari')) return 'Apple Safari';
    elseif (strpos($user_agent, 'Mypal/6') || strpos($user_agent, 'Mypal/7')) return 'Actual Mypal';
    elseif (strpos($user_agent, 'PaleMoon') || strpos($user_agent, 'Mypal/2')) return 'Pale Moon';
    elseif (strpos($user_agent, 'r3dfox')) return 'r3dfox';
    elseif (strpos($user_agent, 'Firefox')) return 'Mozilla Firefox';
    elseif (strpos($user_agent, 'MSIE') || strpos($user_agent, 'Trident/7')) return 'Internet Explorer';
    
    return 'unknown';
}

function get_system_name($user_agent) {
    if (stripos($user_agent, 'FreeBSD') !== false) return 'FreeBSD';
	elseif (stripos($user_agent, 'Macintosh') !== false || stripos($user_agent, 'Mac OS X') !== false) return 'MacOS';
    elseif (stripos($user_agent, 'Ubuntu') !== false) return 'Ubuntu Linux';
	elseif (stripos($user_agent, 'Linux') !== false) return 'Linux';
    
    elseif (strpos($user_agent, 'Windows NT 5.1') !== false) return 'Windows XP';
    elseif (strpos($user_agent, 'Windows NT 5.2') !== false) return 'Windows XP or Server 2003';
    elseif (strpos($user_agent, 'Windows NT 6.0') !== false) return 'Windows Vista';
    elseif (stripos($user_agent, 'Windows NT 6.1') !== false) return 'Windows 7';
    elseif (stripos($user_agent, 'Windows NT 6.2') !== false) return 'Windows 8';
    elseif (stripos($user_agent, 'Windows NT 6.3') !== false) return 'Windows 8.1';
	elseif (stripos($user_agent, 'Windows NT 10.0') !== false) return 'Windows 10 or 11';
    
    return 'unknown';
}

define("UA", get_browser_name($ua_global) . ", " . get_system_name($ua_global));
$user_agent = UA;

?>