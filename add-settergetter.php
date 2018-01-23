#!/usr/bin/php
<?php

set_exception_handler(function($exception) {
    if ($exception instanceof \ReflectionException) {
        die('Error: ' . $exception->getMessage()) . PHP_EOL;
    }
});

extract(parseArgv(), EXTR_SKIP);

validateFile($fileName, $topPathName);

copy($fileName, $fileName . '.bak');
$string = file_get_contents($fileName);
$string = preg_replace('!class\s+([^ ]+)\s+extends\s+[^{]+$!m', 'class $1', $string);
$string = preg_replace('!class\s+([^ ]+)\s+implements\s+[^{]+$!m', 'class $1', $string);
$string = preg_replace('!use\s+([^ ]+)$!m', '// use $1', $string);

file_put_contents($fileName, $string);

require $fileName;

$className = preg_replace('!/!', '\\', $className);

$class = new \ReflectionClass($className);

copy($fileName . '.bak', $fileName);

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
    if (isset($_SERVER['argv'][1])) {
        if (in_array($_SERVER['argv'][1], array('-h', '--h', '--help'))) {
            print '' . PHP_EOL;
            print '[command help]' . PHP_EOL;
            print ' php add-settergetter.php [full path] [class name (with slash)]' . PHP_EOL;
            die();
        }
    }

    $fileName = null;
    $topPathName = 'src';
    $className = '';

    unset($_SERVER['argv'][0]);
    if (isset($_SERVER['argv'][1])) {
        $fileName = $_SERVER['argv'][1];
        unset($_SERVER['argv'][1]);
    }
    if (isset($_SERVER['argv'][2])) {
        $className = $_SERVER['argv'][2];
        unset($_SERVER['argv'][2]);
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
        'className' => $className,
    );
}

/**
 * @param string $fileName
 * @param string $topPathName
 */
function validateFile($fileName, $topPathName)
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
     * Set {$target}.
     *
     * @param {$type} \${$target}
     */
    public function set{$targetWithUcfirst}({$typeForParameter}\${$target})
    {
        \$this->{$target} = \${$target};

        return \$this;
    }
";
        }

        if (in_array("get{$targetWithUcfirst}", get_class_methods($class->getName()))) {
            printf('Notice: get%s() method already exist.' . PHP_EOL, $targetWithUcfirst);
        } else {
            $methodString .= "
    /**
     * Get {$target}.
     *
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
