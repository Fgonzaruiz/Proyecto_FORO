<?php
/** @return array<string, string> */
return json_decode((string)file_get_contents(__DIR__ . '/theme_templates.json'), true, 512, JSON_THROW_ON_ERROR);
