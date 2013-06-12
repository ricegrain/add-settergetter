#!/usr/bin/php
<?php

set_exception_handler(function($exception) {
    if ($exception instanceof \ReflectionException) {
        die('Error: ' . $exception->getMessage()) . PHP_EOL;
    }
});

extract(parseArgv(), EXTR_SKIP);

loadFile($fileName, $topPathName);

$class = new \ReflectionClass(
    preg_replace(array("!^.+/{$topPathName}!", '!/!', '!\.php!'), array('', '\\', ''), $fileName)
);

outputFile(
    $fileName,
    $class->getEndLine(),
    generateMethodString($fileName, $class, $specifiedProperties)
);

/**
 * @return arrray
 */
function parseArgv()
{
    $fileName = null;
    $topPathName = 'src';

    unset($_SERVER['argv'][0]);
    if (isset($_SERVER['argv'][1])) {
        $fileName = $_SERVER['argv'][1];
        unset($_SERVER['argv'][1]);
    }

    $specifiedProperties = array();
    foreach ($_SERVER['argv'] as $argv) {
        if (preg_match('!^topPathName:(.+)$!', $argv, $matches)) {
            $topPathName = $matches[1];
        } else {
            $specifiedProperties[] = $matches[1];
        }
    }

    return array(
        'fileName' => $fileName,
        'topPathName' => $topPathName,
        'specifiedProperties' => $specifiedProperties,
    );
}

/**
 * @param string $fileName
 * @param string $topPathName
 */
function loadFile($fileName, $topPathName)
{
    if (!$fileName) {
        die(
            sprintf('Usage: %s file [specified Property1] [specified Property2] ...', basename($_SERVER['SCRIPT_NAME'])) . PHP_EOL .
            PHP_EOL .
            sprintf(' ex1) %s /path/to/repository/src/Package/Class.php', basename($_SERVER['SCRIPT_NAME'])) . PHP_EOL .
            sprintf('      %s /path/to/repository/src/Package/Class.php id name created_at', basename($_SERVER['SCRIPT_NAME'])) . PHP_EOL .
            sprintf('      %s /path/to/repository/src/Package/Class.php id name created_at topPathName:src', basename($_SERVER['SCRIPT_NAME'])) . PHP_EOL
        );
    }
    if (!file_exists($fileName)) {
        die('Error: file does not exist.' . PHP_EOL);
    }
    if (!preg_match("!/{$topPathName}/!", $fileName)) {
        die('Error: to level path name does not exist.' . PHP_EOL);
    }

    copy($fileName, $fileName . '.bak');
    require $fileName;
}

/**
 * @param string $fileName
 * @param \ReflectionClass $class
 * @return string
 */
function generateMethodString($fileName, \ReflectionClass $class, array $specifiedProperties)
{
    $methodString = '';
    foreach ($class->getProperties() as $property) {
        $target = $property->getName();
        $targetWithUcfirst = ucfirst($target);

        if (count($specifiedProperties) > 0 && !in_array($target, $specifiedProperties)) {
            continue;
        }

        $type = 'string';
        $typeForParameter = '';
        if (preg_match('!@var\s+([^\s]+)\s*!', $property->getDocComment(), $matches)) {
            $type = $matches[1];
            if ($type == 'array') {
                $typeForParameter = 'array ';
            } elseif ($type == '\\DateTime') {
                $typeForParameter = '\\DateTime ';
            } elseif (preg_match('!^\\\\?[A-Z].+!', $type)) {
                $typeForParameter = preg_replace('!^.*\\\\!', '', $type) . ' ';
            }
        }

        if (in_array("set{$targetWithUcfirst}", get_class_methods($class->getName()))) {
            printf('Notice: set%s() method already exist.' . PHP_EOL, $targetWithUcfirst);
        } else {
            $methodString .= "
    /**
     * @param {$type} \${$target}
     */
    public function set{$targetWithUcfirst}({$typeForParameter}\${$target})
    {
        \$this->{$target} = \${$target};
    }
";
        }

        if (in_array("get{$targetWithUcfirst}", get_class_methods($class->getName()))) {
            printf('Notice: get%s() method already exist.' . PHP_EOL, $targetWithUcfirst);
        } else {
            $methodString .= "
    /**
     * @return {$type}
     */
    public function get{$targetWithUcfirst}()
    {
        return \$this->{$target};
    }
";
        }
    }

    return $methodString;
}

/**
 * @param string $fileName
 * @param integer $insertPointLineNumber
 * @param string $methodString
 */
function outputFile($fileName, $insertPointLineNumber, $methodString)
{
    $fileString = '';
    $handle = fopen($fileName, 'r');
    if ($handle) {
        $line = 0;
        while (($buffer = fgets($handle, 4096)) !== false) {
            ++$line;
            $fileString .= $buffer;
            if ($line == $insertPointLineNumber - 1) {
                $fileString .= $methodString;
            }
        }
        fclose($handle);
    }

    file_put_contents($fileName, $fileString);
}

/*
 * Local Variables:
 * mode: php
 * coding: utf-8
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * indent-tabs-mode: nil
 * End:
 */
