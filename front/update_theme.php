<?php
$xml_file = 'Default-theme.xml';
if (!file_exists($xml_file)) {
    die("XML not found.\n");
}

$xml_content = file_get_contents($xml_file);

$templates = [
    'headerinclude' => 'templates/mybb/global/headerinclude.html',
    'header' => 'templates/mybb/global/header.html',
    'header_welcomeblock_member' => 'templates/mybb/global/header_welcomeblock_member.html',
    'header_welcomeblock_guest' => 'templates/mybb/global/header_welcomeblock_guest.html',
    'postbit' => 'templates/mybb/showthread/postbit.html',
    'postbit_author_user' => 'templates/mybb/showthread/postbit_author_user.html',
    'showthread' => 'templates/mybb/showthread/showthread.html',
    'showthread_threadlist' => 'templates/mybb/showthread/showthread_threadlist.html',
    'newreply' => 'templates/mybb/newreply/newreply.html',
    'newthread' => 'templates/mybb/newthread/newthread.html',
    'showthread_quickreply' => 'templates/mybb/showthread/showthread_quickreply.html',
    'forumdisplay_thread' => 'templates/mybb/forumdisplay/forumdisplay_thread.html',
    'forumdisplay_threadlist' => 'templates/mybb/forumdisplay/forumdisplay_threadlist.html',
    'index' => 'templates/mybb/index/index.html',
    'index_boardstats' => 'templates/mybb/index/index_boardstats.html'
];

foreach ($templates as $name => $path) {
    if (file_exists($path)) {
        $html_content = file_get_contents($path);
        // Ensure standard line endings
        $html_content = str_replace("\r\n", "\n", $html_content);
        
        // Find the template in XML
        // Pattern: <template name="NAME" ...><![CDATA[ ... ]]></template>
        $pattern = '/(<template\s+name="' . preg_quote($name, '/') . '"[^>]*><!\[CDATA\[)(.*?)(\]\]><\/template>)/s';
        
        $xml_content = preg_replace_callback($pattern, function($matches) use ($html_content) {
            return $matches[1] . $html_content . "\n\t\t" . $matches[3];
        }, $xml_content);
        
        echo "Updated $name\n";
    } else {
        echo "File not found: $path\n";
    }
}

file_put_contents($xml_file, $xml_content);
echo "Updated templates in XML.\n";

// Update global.css with contents of rpg_custom.css
$rpg_css_path = '../back/forum/rpg_custom.css';
if (file_exists($rpg_css_path)) {
    $xml_content = file_get_contents($xml_file);
    $rpg_css_content = file_get_contents($rpg_css_path);
    $rpg_css_content = str_replace("\r\n", "\n", $rpg_css_content);
    
    // Locate the global.css stylesheet tag
    $css_pattern = '/(<stylesheet\s+name="global\.css"[^>]*><\!\[CDATA\[)(.*?)(\<\/stylesheet\>)/s';
    
    $xml_content = preg_replace_callback($css_pattern, function($matches) use ($rpg_css_content) {
        $prefix = $matches[1];
        $content = $matches[2];
        $suffix = $matches[3];
        
        $marker = "/* RPG Premium Modern Theme */";
        $marker_pos = strpos($content, $marker);
        
        if ($marker_pos !== false) {
            $base_css = substr($content, 0, $marker_pos);
        } else {
            $base_css = $content . "\n";
        }
        
        // Remove trailing CDATA end if it somehow existed or was messy
        $base_css = preg_replace('/\]\]>\s*$/', '', $base_css);
        
        $new_css = $base_css . $marker . "\n" . $rpg_css_content;
        
        // Return prefix + new CSS + CDATA end + suffix (</stylesheet>)
        return $prefix . $new_css . "]]>\n\t\t" . $suffix;
    }, $xml_content);
    
    file_put_contents($xml_file, $xml_content);
    echo "Updated global.css stylesheet in XML from rpg_custom.css\n";
} else {
    echo "rpg_custom.css not found, skipping stylesheet update.\n";
}

echo "XML updated successfully.\n";
echo "Run: php validate_theme_security.php (or powershell validate_theme_security.ps1) before importing.\n";
