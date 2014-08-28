<?php
function handleError($errno, $errstr, $errfile, $errline)
{
    if ($errno == E_NOTICE || $errno == E_WARNING) {
        throw new Exception("$errstr in $errfile @ line $errline", $errno);
    }
}

set_error_handler('handleError');

try {
    echo "Initializing... ";
    define('ROOT_DIR', dirname(__DIR__));
    define('SOURCE_DIR', ROOT_DIR . DIRECTORY_SEPARATOR . 'source-data');
    define('DESTINATION_DIR', ROOT_DIR . DIRECTORY_SEPARATOR . 'code' . DIRECTORY_SEPARATOR . 'data');
    define('TESTS_DIR', ROOT_DIR . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'dataFiles');

    if (isset($argv)) {
        foreach ($argv as $i => $arg) {
            if ($i > 0) {
                if ((strcasecmp($arg, 'debug') === 0) || (strcasecmp($arg, '--debug') === 0)) {
                    defined('DEBUG') or define('DEBUG', true);
                }
                if ((strcasecmp($arg, 'full') === 0) || (strcasecmp($arg, '--full') === 0)) {
                    defined('FULL_JSON') or define('FULL_JSON', true);
                }
                if ((strcasecmp($arg, 'post-clean') === 0) || (strcasecmp($arg, '--post-clean') === 0)) {
                    defined('POST_CLEAN') or define('POST_CLEAN', true);
                }
            }
        }
    }
    defined('DEBUG') or define('DEBUG', false);
    defined('FULL_JSON') or define('FULL_JSON', false);
    define('LOCAL_ZIP_FILE', SOURCE_DIR . DIRECTORY_SEPARATOR . (FULL_JSON ? 'data_full.zip' : 'data.zip'));
    define('SOURCE_DIR_DATA', SOURCE_DIR . DIRECTORY_SEPARATOR . (FULL_JSON ? 'data_full' : 'data'));
    defined('POST_CLEAN') or define('POST_CLEAN', false);

    if (!is_dir(SOURCE_DIR)) {
        if (mkdir(SOURCE_DIR, 0777, true) === false) {
            echo "Failed to create " . SOURCE_DIR . "\n";
            die(1);
        }
    }
    if (is_dir(DESTINATION_DIR)) {
        deleteFromFilesystem(DESTINATION_DIR);
    }
    if (mkdir(DESTINATION_DIR, 0777, false) === false) {
        echo "Failed to create " . DESTINATION_DIR . "\n";
        die(1);
    }
    if (!is_dir(TESTS_DIR)) {
        if (mkdir(TESTS_DIR, 0777, false) === false) {
            echo "Failed to create " . TESTS_DIR . "\n";
            die(1);
        }
    }

    echo "done.\n";
    if (!is_dir(SOURCE_DIR_DATA)) {
        if (!is_file(LOCAL_ZIP_FILE)) {
            downloadCLDR();
        }
        ExtractCLDR();
    }
    copyData();
    if (POST_CLEAN) {
        deleteFromFilesystem(SOURCE_DIR_DATA);
    }
    die(0);
} catch (Exception $x) {
    echo $x->getMessage(), "\n";
    die(1);
}

function downloadCLDR()
{
    $remoteURL = FULL_JSON ? 'http://unicode.org/Public/cldr/25/json_full.zip' : 'http://www.unicode.org/Public/cldr/25/json.zip';
    $zipFrom = null;
    $zipTo = null;
    echo "Downloading $remoteURL... ";
    try {
        $zipFrom = fopen($remoteURL, 'rb');
        if ($zipFrom === false) {
            throw new Exception("Failed to read $remoteURL");
        }
        $zipTo = fopen(LOCAL_ZIP_FILE, 'wb');
        if ($zipTo === false) {
            throw new Exception("Failed to create " . LOCAL_ZIP_FILE);
        }
        while (!feof($zipFrom)) {
            $buffer = fread($zipFrom, 4096);
            if ($buffer === false) {
                throw new Exception("Failed to fetch data from $remoteURL");
            }
            if (fwrite($zipTo, $buffer) === false) {
                throw new Exception("Failed to write data to " . LOCAL_ZIP_FILE);
            }
        }
        fclose($zipTo);
        $zipTo = null;
        fclose($zipFrom);
        $zipFrom = null;
        echo "done.\n";
    } catch (Exception $x) {
        if ($zipTo) {
            fclose($zipTo);
            $zipTo = null;
        }
        if ($zipFrom) {
            fclose($zipFrom);
            $zipFrom = null;
        }
        if (is_file(LOCAL_ZIP_FILE)) {
            unlink(LOCAL_ZIP_FILE);
        }
        throw $x;
    }
}

function extractCLDR()
{
    $zip = null;
    echo "Extracting " . LOCAL_ZIP_FILE . "... ";
    try {
        $zip = new ZipArchive();
        $rc = $zip->open(LOCAL_ZIP_FILE);
        if ($rc !== true) {
            throw new Exception("Opening " . LOCAL_ZIP_FILE . " failed with return code $rc");
        }
        $zip->extractTo(SOURCE_DIR_DATA);
        $zip->close();
        $zip = null;
        echo "done.\n";
    } catch (Exception $x) {
        if ($zip) {
            @$zip->close();
            $zip = null;
        }
        if (is_dir(SOURCE_DIR_DATA)) {
            try {
                deleteFromFilesystem(SOURCE_DIR_DATA);
            } catch (Exception $foo) {
            }
        }
        throw $x;
    }
}

function copyData()
{
    $copy = array(
        'ca-gregorian.json' => array('kind' => 'main', 'save-as' => 'calendar.json', 'roots' => array('dates', 'calendars', 'gregorian')),
        'timeZoneNames.json' => array('kind' => 'main', 'roots' => array('dates', 'timeZoneNames')),
        'listPatterns.json' => array('kind' => 'main', 'roots' => array('listPatterns')),
        'units.json' => array('kind' => 'main', 'roots' => array('units')),
        'dateFields.json' => array('kind' => 'main', 'roots' => array('dates', 'fields')),
        'languages.json' => array('kind' => 'main', 'roots' => array('localeDisplayNames', 'languages')),
        'territories.json' => array('kind' => 'main', 'roots' => array('localeDisplayNames', 'territories')),
        'localeDisplayNames.json' => array('kind' => 'main', 'roots' => array('localeDisplayNames')),
        /*
        'characters.json' => array('kind' => 'main', 'roots' => array('characters')),
        'contextTransforms.json' => array('kind' => 'main', 'roots' => array('contextTransforms')),
        'currencies.json' => array('kind' => 'main', 'roots' => array('numbers', 'currencies')),
        'delimiters.json' => array('kind' => 'main', 'roots' => array('delimiters')),
        'layout.json' => array('kind' => 'main', 'roots' => array('layout', 'orientation')),
        'measurementSystemNames.json' => array('kind' => 'main', 'roots' => array('localeDisplayNames', 'measurementSystemNames')),
        'numbers.json' => array('kind' => 'main', 'roots' => array('numbers')),
        'scripts.json' => array('kind' => 'main', 'roots' => array('localeDisplayNames', 'scripts')),
        'transformNames.json' => array('kind' => 'main', 'roots' => array('localeDisplayNames', 'transformNames')),
        'variants.json' => array('kind' => 'main', 'roots' => array('localeDisplayNames', 'variants')),
        */
        'weekData.json' => array('kind' => 'supplemental', 'roots' => array('supplemental', 'weekData')),
        'parentLocales.json' => array('kind' => 'supplemental', 'roots' => array('supplemental', 'parentLocales', 'parentLocale')),
        'likelySubtags.json' => array('kind' => 'supplemental', 'roots' => array('supplemental', 'likelySubtags')),
        'territoryContainment.json' => array('kind' => 'supplemental', 'roots' => array('supplemental', 'territoryContainment')),
        'metaZones.json' => array('kind' => 'supplemental', 'roots' => array('supplemental', 'metaZones')),
        'plurals.json' => array('kind' => 'supplemental', 'roots' => array('supplemental', 'plurals-type-cardinal')),
    );
    $src = SOURCE_DIR_DATA . DIRECTORY_SEPARATOR . 'main';
    $locales = scandir($src);
    if ($locales === false) {
        throw new Exception("Failed to retrieve the file list of $src");
    }
    $locales = array_diff($locales, array('.', '..'));
    foreach ($locales as $locale) {
        if (is_dir($src . DIRECTORY_SEPARATOR . $locale)) {
            echo "Parsing locale $locale... ";
            $destFolder = DESTINATION_DIR . DIRECTORY_SEPARATOR . $locale;
            if (is_dir($destFolder)) {
                deleteFromFilesystem($destFolder);
            }
            if (mkdir($destFolder) === false) {
                throw new Exception("Failed to create $destFolder\n");
            }
            foreach ($copy as $copyFrom => $info) {
                if ($info['kind'] === 'main') {
                    $copyTo = array_key_exists('save-as', $info) ? $info['save-as'] : $copyFrom;
                    if ($copyTo === false) {
                        $copyTo = $copyFrom;
                    }
                    $dstFile = $destFolder . DIRECTORY_SEPARATOR . $copyTo;
                    $useLocale = $locale;
                    $srcFile = $src . DIRECTORY_SEPARATOR . $useLocale . DIRECTORY_SEPARATOR . $copyFrom;
                    if (!is_file($srcFile)) {
                        $useLocale = 'en';
                        $srcFile = $src . DIRECTORY_SEPARATOR . $useLocale . DIRECTORY_SEPARATOR . $copyFrom;
                        if (!is_file($srcFile)) {
                            throw new Exception("File not found: $srcFile");
                        }
                    }
                    $info['roots'] = array_merge(array('main', $useLocale), $info['roots']);
                    $info['unsetByPath'] = array_merge(
                        isset($info['unsetByPath']) ? $info['unsetByPath'] : array(),
                        array(
                            "/main/$useLocale" => array('identity')
                        )
                    );
                    copyDataFile($srcFile, $info, $dstFile);
                }
            }
            echo "done.\n";
        }
    }
    echo "Parsing supplemental files... ";
    $src = SOURCE_DIR_DATA . DIRECTORY_SEPARATOR . 'supplemental';
    foreach ($copy as $copyFrom => $info) {
        if ($info['kind'] === 'supplemental') {
            $copyTo = array_key_exists('save-as', $info) ? $info['save-as'] : $copyFrom;
            $dstFile = DESTINATION_DIR . DIRECTORY_SEPARATOR . $copyTo;
            $srcFile = $src . DIRECTORY_SEPARATOR . $copyFrom;
            if (!is_file($srcFile)) {
                throw new Exception("File not found: $srcFile");
            }
            $info['unsetByPath'] = array_merge(
                isset($info['unsetByPath']) ? $info['unsetByPath'] : array(),
                array(
                    '/supplemental' => array('version', 'generation')
                )
            );
            copyDataFile($srcFile, $info, $dstFile);
        }
    }
    echo "done.\n";
}

function copyDataFile($srcFile, $info, $dstFile)
{
    $json = file_get_contents($srcFile);
    if ($json === false) {
        throw new Exception("Failed to read from $srcFile");
    }
    $data = json_decode($json, true);
    if (is_null($data)) {
        throw new Exception("Failed to decode data in $srcFile");
    }
    $path = '';
    foreach ($info['roots'] as $root) {
        if (!is_array($data)) {
            throw new Exception("Decoded data should be an array in $srcFile (path: $path)");
        }
        if (isset($info['unsetByPath'][$path])) {
            foreach ($info['unsetByPath'][$path] as $node) {
                if (array_key_exists($node, $data)) {
                    unset($data[$node]);
                }
            }
        }
        if ((count($data) !== 1) || (!array_key_exists($root, $data))) {
            throw new Exception("Invalid data in $srcFile:\nExpected one array with the sole key '$root' (path: $path), keys found: " . implode(', ', array_keys($data)));
        }
        $data = $data[$root];
        $path .= "/$root";
    }
    if (!is_array($data)) {
        throw new Exception("Decoded data should be an array in $srcFile (path: $path)");
    }
    $jsonFlags = 0;
    if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
        $jsonFlags |= JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if (DEBUG) {
            $jsonFlags |= JSON_PRETTY_PRINT;
        }
    }
    switch (basename($dstFile)) {
        case 'calendar.json':
            unset($data['dateTimeFormats']['availableFormats']);
            unset($data['dateTimeFormats']['appendItems']);
            unset($data['dateTimeFormats']['intervalFormats']);
            foreach (array_keys($data['dateTimeFormats']) as $key) {
                $data['dateTimeFormats'][$key] = toPhpSprintf($data['dateTimeFormats'][$key]);
            }
            foreach (array('eraNames' => 'wide', 'eraAbbr' => 'abbreviated', 'eraNarrow' => 'narrow') as $keyFrom => $keyTo) {
                if (array_key_exists($keyFrom, $data['eras'])) {
                    $data['eras'][$keyTo] = $data['eras'][$keyFrom];
                    unset($data['eras'][$keyFrom]);
                }
            }
            break;
        case 'weekData.json':
            foreach (array_keys($data['minDays']) as $key) {
                $value = $data['minDays'][$key];
                if (!preg_match('/^[0-9]+$/', $value)) {
                    throw new Exception("Bad number: $value");
                }
                $data['minDays'][$key] = intval($value);
            }
            $dict = array('sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat');
            foreach (array_keys($data['firstDay']) as $key) {
                $val = array_search($data['firstDay'][$key], $dict, true);
                if ($val === false) {
                    throw new Exception("Unknown weekday name: {$data['firstDay'][$key]}");
                }
                $data['firstDay'][$key] = $val;
            }
            unset($data['firstDay-alt-variant']);
            unset($data['weekendStart']);
            unset($data['weekendEnd']);
            break;
        case 'territoryContainment.json':
            foreach (array_keys($data) as $key) {
                if (array_key_exists('_grouping', $data[$key])) {
                    unset($data[$key]['_grouping']);
                }
                if (array_key_exists('_contains', $data[$key])) {
                    $data[$key]['contains'] = $data[$key]['_contains'];
                    unset($data[$key]['_contains']);
                }
                if (strpos($key, '-status-') !== false) {
                    unset($data[$key]);
                }
            }
            break;
        case 'metaZones.json':
            if ((count($data['metazoneInfo']) !== 1) || (!array_key_exists('timezone', $data['metazoneInfo']))) {
                throw new Exception('Invalid metazoneInfo node');
            }
            $data['metazoneInfo'] = $data['metazoneInfo']['timezone'];
            foreach ($data['metazoneInfo'] as $id0 => $info0) {
                $values1 = null;
                foreach ($info0 as $id1 => $info1) {
                    if (is_int($id1)) {
                        $info1 = fixMetazoneInfo($info1);
                    } else {
                        foreach ($info1 as $id2 => $info2) {
                            if (is_int($id2)) {
                                $info2 = fixMetazoneInfo($info2);
                            } else {
                                foreach ($info2 as $id3 => $info3) {
                                    if (is_int($id3)) {
                                        $info3 = fixMetazoneInfo($info3);
                                    } else {
                                        throw new Exception('Invalid metazoneInfo node');
                                    }
                                    $info2[$id3] = $info3;
                                }
                            }
                            $info1[$id2] = $info2;
                        }
                    }
                    $info0[$id1] = $info1;
                }
                $data['metazoneInfo'][$id0] = $info0;
            }
            break;
        case 'timeZoneNames.json':
            foreach (array('gmtFormat', 'gmtZeroFormat', 'regionFormat', 'regionFormat-type-standard', 'regionFormat-type-daylight', 'fallbackFormat') as $k) {
                if (array_key_exists($k, $data)) {
                    $data[$k] = toPhpSprintf($data[$k]);
                }
            }
            break;
        case 'listPatterns.json':
            $keys = array_keys($data);
            foreach ($keys as $key) {
                if (!preg_match('/^listPattern-type-(.+)$/', $key, $m)) {
                    throw new Exception("Invalid node '$key' in " . $dstFile);
                }
                foreach (array_keys($data[$key]) as $k) {
                    $data[$key][$k] = toPhpSprintf($data[$key][$k]);
                }
                $data[$m[1]] = $data[$key];
                unset($data[$key]);
            }
            break;
        case 'plurals.json':
            $testData = array();
            foreach ($data as $l => $lData) {
                $testData[$l] = array();
                $keys = array_keys($lData);
                foreach ($keys as $key) {
                    if (!preg_match('/^pluralRule-count-(.+)$/', $key, $m)) {
                        throw new Exception("Invalid node '$key' in " . $dstFile);
                    }
                    $rule = $m[1];
                    $testData[$l][$rule] = array();
                    $vOriginal = $lData[$key];
                    $examples = explode('@', $vOriginal);
                    $v = trim(array_shift($examples));
                    foreach ($examples as $example) {
                        list($exampleNumberType, $exampleValues) = explode(' ', $example, 2);
                        switch ($exampleNumberType) {
                            case 'integer':
                            case 'decimal':
                                $exampleValues = preg_replace('/, …$/', '', $exampleValues);
                                $exampleValuesParsed = array();
                                foreach (explode(', ', trim($exampleValues)) as $ev) {
                                    if (preg_match('/^[+\-]?\d+$/', $ev)) {
                                        $exampleValuesParsed[] = $ev;
                                        $exampleValuesParsed[] = intval($ev);
                                    } elseif (preg_match('/^[+\-]?\d+\.\d+$/', $ev)) {
                                        $exampleValuesParsed[] = $ev;
                                    } elseif (preg_match('/^([+\-]?\d+)~([+\-]?\d+)$/', $ev, $m)) {
                                        $exampleValuesParsed[] = $m[1];
                                        $exampleValuesParsed[] = intval($m[1]);
                                        $exampleValuesParsed[] = $m[2];
                                        $exampleValuesParsed[] = intval($m[2]);
                                    } elseif (preg_match('/^([+\-]?\d+(\.\d+)?)~([+\-]?\d+(\.\d+)?)$/', $ev, $m)) {
                                        $exampleValuesParsed[] = $m[1];
                                        $exampleValuesParsed[] = $m[3];
                                    } elseif ($ev !== '…') {
                                        throw new Exception("Invalid node '$key' in $dstFile: $vOriginal");
                                    }
                                }
                                $testData[$l][$rule] = $exampleValuesParsed;
                                break;
                            default:
                                throw new Exception("Invalid node '$key' in $dstFile: $vOriginal");
                        }
                    }
                    if ($rule === 'other') {
                        if (strlen($v) > 0) {
                            throw new Exception("Invalid node '$key' in $dstFile: $vOriginal");
                        }
                    } else {
                        $v = str_replace(' = ', ' == ', $v);
                        $map = array('==' => 'true', '!=' => 'false');
                        foreach (array('^', ' and ', ' or ') as $pre) {
                            while(preg_match(
                                '/' . $pre . '(([nivfwft]( % \\d+)?) (==|!=) ((\\d+)(((\\.\\.)|,)+(\\d+))+))/',
                                $v,
                                $m
                            )) {
                                $found = $m[1];
                                $leftyPart = $m[2]; // eg 'n % 10'
                                $operator = $m[4]; // eg '=='
                                $ranges = explode(',', $m[5]);
                                foreach (array_keys($ranges) as $j) {
                                    if (preg_match('/^(\\d+)\\.\\.(\\d+)$/', $ranges[$j], $m)) {
                                        $ranges[$j] = "array({$m[1]}, {$m[2]})";
                                    }
                                }
                                $v = str_replace($found, "static::inRange($leftyPart, {$map[$operator]}, " . implode(', ', $ranges) . ")", $v);
                            }
                        }
                        if (strpos($v, '..') !== false) {
                            throw new Exception("Invalid node '$key' in $dstFile: $vOriginal");
                        }
                        foreach(array(
                            'n' => '%1$s', // absolute value of the source number (integer and decimals).
                            'i' => '%2$s', // integer digits of n
                            'v' => '%3$s', // number of visible fraction digits in n, with trailing zeros.
                            'w' => '%4$s', // number of visible fraction digits in n, without trailing zeros.
                            'f' => '%5$s', // visible fractional digits in n, with trailing zeros.
                            't' => '%6$s', // visible fractional digits in n, without trailing zeros.
                        ) as $from => $to) {
                            $v = preg_replace('/^' . $from .' /', "$to ", $v);
                            $v = preg_replace("/^$from /", "$to ", $v);
                            $v = str_replace(" $from ", " $to ", $v);
                            $v = str_replace("($from, ", "($to, ", $v);
                            $v = str_replace("($from ", "($to ", $v);
                            $v = str_replace(" $from,", " $to,", $v);
                        }
                        $v = str_replace(' % ', ' %% ', $v);
                        $lData[$rule] = $v;
                    }
                    unset($lData[$key]);
                }
                $data[$l] = $lData;
            }
            $testJson = json_encode($testData, $jsonFlags);
            if ($testJson === false) {
                throw new Exception("Failed to serialize test data for $srcFile");
            }
            $testDataFile = TESTS_DIR . DIRECTORY_SEPARATOR . basename($dstFile);
            if (is_file($testDataFile)) {
                deleteFromFilesystem($testDataFile);
            }
            if (file_put_contents($testDataFile, $testJson) === false) {
                throw new Exception("Failed write to $testDataFile");
            }
            break;
        case 'units.json':
            foreach (array_keys($data) as $width) {
                switch ($width) {
                    case 'long':
                    case 'short':
                    case 'narrow':
                    case 'long':
                        foreach (array_keys($data[$width]) as $unitKey) {
                            switch ($unitKey) {
                                case 'per':
                                    if (implode('|', array_keys(($data[$width][$unitKey]))) !== 'compoundUnitPattern') {
                                        throw new Exception("Invalid node '$width/$unitKey' in " . $dstFile);
                                    }
                                    $data[$width]['_compoundPattern'] = toPhpSprintf($data[$width][$unitKey]['compoundUnitPattern']);
                                    unset($data[$width][$unitKey]);
                                    break;
                                default:
                                    if (!preg_match('/^(\\w+)?-(.+)$/', $unitKey, $m)) {
                                        throw new Exception("Invalid node '$width/$unitKey' in " . $dstFile);
                                    }
                                    $unitKind = $m[1];
                                    $unitName = $m[2];
                                    if (!array_key_exists($unitKind, $data[$width])) {
                                        $data[$width][$unitKind] = array();
                                    }
                                    if (!array_key_exists($unitName, $data[$width][$unitKind])) {
                                        $data[$width][$unitKind][$unitName] = array();
                                    }
                                    foreach (array_keys($data[$width][$unitKey]) as $pluralRuleSrc) {
                                        if (!preg_match('/^unitPattern-count-(.+)$/', $pluralRuleSrc, $m)) {
                                            throw new Exception("Invalid node '$width/$unitKey/$pluralRuleSrc' in " . $dstFile);
                                        }
                                        $pluralRule = $m[1];
                                        $data[$width][$unitKind][$unitName][$pluralRule] = toPhpSprintf($data[$width][$unitKey][$pluralRuleSrc]);
                                    }
                                    unset($data[$width][$unitKey]);
                                    break;
                            }
                        }
                        break;
                    default:
                        if (preg_match('/^durationUnit-type-(.+)/', $width, $m)) {
                            if (implode('|', array_keys(($data[$width]))) !== 'durationUnitPattern') {
                                throw new Exception("Invalid node '$width' in " . $dstFile);
                            }
                            $t = $m[1];
                            if (!array_key_exists('_durationPattern', $data)) {
                                $data['_durationPattern'] = array();
                            }
                            $data['_durationPattern'][$t] = $data[$width]['durationUnitPattern'];
                            unset($data[$width]);
                        } else {
                            throw new Exception("Invalid node '$width' in " . $dstFile);
                        }
                        break;
                }
            }
            break;
        case 'localeDisplayNames.json':
            if (!array_key_exists('localeDisplayPattern', $data)) {
                throw new Exception("Missing node 'localeDisplayPattern' in " . $dstFile);
            }
            foreach (array_keys($data['localeDisplayPattern']) as $k) {
                $data['localeDisplayPattern'][$k] = toPhpSprintf($data['localeDisplayPattern'][$k]);
            }
            if (!array_key_exists('codePatterns', $data)) {
                throw new Exception("Missing node 'codePatterns' in " . $dstFile);
            }
            foreach (array_keys($data['codePatterns']) as $k) {
                $data['codePatterns'][$k] = toPhpSprintf($data['codePatterns'][$k]);
            }
            break;
    }
    $json = json_encode($data, $jsonFlags);
    if ($json === false) {
        throw new Exception("Failed to serialize data of $srcFile");
    }
    if (is_file($dstFile)) {
        deleteFromFilesystem($dstFile);
    }
    if (file_put_contents($dstFile, $json) === false) {
        throw new Exception("Failed write to $dstFile");
    }
}

function deleteFromFilesystem($path)
{
    if (is_file($path)) {
        if (unlink($path) === false) {
            throw new Exception("Failed to delete file $path");
        }
    } else {
        $contents = scandir($path);
        if ($contents === false) {
            throw new Exception("Failed to retrieve the file list of $path");
        }
        foreach (array_diff($contents, array('.', '..')) as $item) {
            deleteFromFilesystem($path . DIRECTORY_SEPARATOR . $item);
        }
        if (rmdir($path) === false) {
            throw new Exception("Failed to delete directory $path");
        }
    }
}

function toPhpSprintf($fmt)
{
    $result = $fmt;
    if (is_string($fmt)) {
        $result = str_replace('%', '%%', $result);
        $result = preg_replace_callback(
            '/\\{(\\d+)\\}/',
            function ($matches) {
                return '%' . (1 + intval($matches[1])) . '$s';
            },
            $fmt
        );
    }

    return $result;
}

function fixMetazoneInfo($a)
{
    if ((count(array_keys($a)) !== 1) || (!array_key_exists('usesMetazone', $a))) {
        throw new Exception('Invalid metazoneInfo node');
    }
    $a = $a['usesMetazone'];
    foreach (array_keys($a) as $key) {
        switch ($key) {
            case '_mzone':
            case '_from':
            case '_to':
                $a[substr($key, 1)] = $a[$key];
                unset($a[$key]);
                break;
            default:
                throw new Exception('Invalid metazoneInfo node');
        }
    }

    return $a;
}
