<?php
function get_browser_name($user_agent)
{
    if (strpos($user_agent, 'Opera') || strpos($user_agent, 'OPR/')) return 'opera';
    elseif (strpos($user_agent, 'Edge')) return 'old edge';
    elseif (strpos($user_agent, 'Chrome')) return 'chrome/chromium based';
    elseif (strpos($user_agent, 'Safari')) return 'safari';
    elseif (strpos($user_agent, 'Firefox')) return 'firefox';
    elseif (strpos($user_agent, 'MSIE') || strpos($user_agent, 'Trident/7')) return 'internet explorer';
    
    return 'n/a';
}

$user_agent = get_browser_name($_SERVER['HTTP_USER_AGENT']);

?>