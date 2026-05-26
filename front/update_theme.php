<?php
$xml_file = 'Default-theme.xml';
if (!file_exists($xml_file)) {
    die("XML not found.\n");
}

$xml_content = file_get_contents($xml_file);

$templates = [
    'headerinclude' => 'templates/mybb/global/headerinclude.html',
    'postbit' => 'templates/mybb/showthread/postbit.html',
    'newreply' => 'templates/mybb/newreply/newreply.html',
    'newthread' => 'templates/mybb/newthread/newthread.html',
    'showthread_quickreply' => 'templates/mybb/showthread/showthread_quickreply.html'
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
echo "XML updated successfully.\n";
