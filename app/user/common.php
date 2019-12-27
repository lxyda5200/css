<?php


if (!function_exists('bccomp')) {

    /**
     * 支持正数和负数的比较
     * ++ -- +-
     * @param $numOne
     * @param $numTwo
     * @param null $scale
     * @return int|string
     */
    function bccomp($numOne, $numTwo, $scale = null)
    {
        //先判断是传过来的两个变量是否合法,不合法都返回'0'
        if (!preg_match("/^([+-]?)\d+(\.\d+)?$/", $numOne, $numOneSign) ||
            !preg_match("/^([+-]?)\d+(\.\d+)?$/", $numTwo, $numTwoSign)
        ) {
            return '0';
        }

        $signOne = $numOneSign[1] === '-' ? '-' : '+';
        $signTwo = $numTwoSign[1] === '-' ? '-' : '+';

        if ($signOne !== $signTwo) {    //异号
            if ($signOne === '-' && $signTwo === '+') {
                return -1;
            } else if ($signOne === '+' && $signTwo === '-') {
                return 1;
            } else {
                return '0';
            }
        } else {  //同号
            //两个负数比较
            if ($signOne === "-" && $signTwo === '-') {
                $numOne = abs($numOne);
                $numTwo = abs($numTwo);
                $flag = bccompPositiveNum($numOne, $numTwo, $scale);
                if ($flag === 0) {
                    return 0;
                } else if ($flag === 1) {
                    return -1;
                } else if ($flag === -1) {
                    return 1;
                } else {
                    return '0';
                }
            } else {    //两个正数比较
                //两正数比较
                return bccompPositiveNum($numOne, $numTwo, $scale);
            }
        }
    }
}

if (!function_exists('bccompPositiveNum')) {
    /**
     * 比较正数的大小写问题
     * @param $numOne
     * @param $numTwo
     * @param null $scale
     * @return int|string
     */
    function bccompPositiveNum($numOne, $numTwo, $scale = null)
    {
        // check if they're valid positive numbers, extract the whole numbers and decimals
        if (!preg_match("/^\+?(\d+)(\.\d+)?$/", $numOne, $tmpOne) ||
            !preg_match("/^\+?(\d+)(\.\d+)?$/", $numTwo, $tmpTwo)
        ) {
            return '0';
        }

        // remove leading zeroes from whole numbers
        $numOne = ltrim($tmpOne[1], '0');
        $numTwo = ltrim($tmpTwo[1], '0');

        // first, we can just check the lengths of the numbers, this can help save processing time
        // if $numOne is longer than $numTwo, return 1.. vice versa with the next step.
        if (strlen($numOne) > strlen($numTwo)) {
            return 1;
        } else {
            if (strlen($numOne) < strlen($numTwo)) {
                return -1;
            } // if the two numbers are of equal length, we check digit-by-digit
            else {

                // remove ending zeroes from decimals and remove point
                $Dec1 = isset($tmpOne[2]) ? rtrim(substr($tmpOne[2], 1), '0') : '';
                $Dec2 = isset($tmpTwo[2]) ? rtrim(substr($tmpTwo[2], 1), '0') : '';

                // if the user defined $scale, then make sure we use that only
                if ($scale != null) {
                    $Dec1 = substr($Dec1, 0, $scale);
                    $Dec2 = substr($Dec2, 0, $scale);
                }

                // calculate the longest length of decimals
                $DLen = max(strlen($Dec1), strlen($Dec2));

                // append the padded decimals onto the end of the whole numbers
                $numOne .= str_pad($Dec1, $DLen, '0');
                $numTwo .= str_pad($Dec2, $DLen, '0');

                // check digit-by-digit, if they have a difference, return 1 or -1 (greater/lower than)
                for ($i = 0; $i < strlen($numOne); ++$i) {
                    if ((int)$numOne{$i} > (int)$numTwo{$i}) {
                        return 1;
                    } elseif ((int)$numOne{$i} < (int)$numTwo{$i}) {
                        return -1;
                    }
                }

                // if the two numbers have no difference (they're the same).. return 0
                return 0;
            }
        }
    }
}




/**
 * 获取汉字首字母函数
 * @param $str
 * @return null|string
 */
function getFirstCharter($str)
{
    if (empty($str)) {
        return '';
    }

    $fchar = ord($str{0});

    if ($fchar >= ord('A') && $fchar <= ord('z'))
        return strtoupper($str{0});

    $s1 = iconv('UTF-8', 'gb2312', $str);

    $s2 = iconv('gb2312', 'UTF-8', $s1);

    $s = $s2 == $str ? $s1 : $str;

    $asc = ord($s{0}) * 256 + ord($s{1}) - 65536;

    if ($asc >= -20319 && $asc <= -20284)
        return 'A';

    if ($asc >= -20283 && $asc <= -19776)
        return 'B';

    if ($asc >= -19775 && $asc <= -19219)
        return 'C';

    if ($asc >= -19218 && $asc <= -18711)
        return 'D';

    if ($asc >= -18710 && $asc <= -18527)
        return 'E';

    if ($asc >= -18526 && $asc <= -18240)
        return 'F';

    if ($asc >= -18239 && $asc <= -17923)
        return 'G';

    if ($asc >= -17922 && $asc <= -17418)
        return 'H';

    if ($asc >= -17417 && $asc <= -16475)
        return 'J';

    if ($asc >= -16474 && $asc <= -16213)
        return 'K';

    if ($asc >= -16212 && $asc <= -15641)
        return 'L';

    if ($asc >= -15640 && $asc <= -15166)
        return 'M';

    if ($asc >= -15165 && $asc <= -14923)
        return 'N';

    if ($asc >= -14922 && $asc <= -14915)
        return 'O';

    if ($asc >= -14914 && $asc <= -14631)
        return 'P';

    if ($asc >= -14630 && $asc <= -14150)
        return 'Q';

    if ($asc >= -14149 && $asc <= -14091)
        return 'R';

    if ($asc >= -14090 && $asc <= -13319)
        return 'S';

    if ($asc >= -13318 && $asc <= -12839)
        return 'T';

    if ($asc >= -12838 && $asc <= -12557)
        return 'W';

    if ($asc >= -12556 && $asc <= -11848)
        return 'X';

    if ($asc >= -11847 && $asc <= -11056)
        return 'Y';

    if ($asc >= -11055 && $asc <= -10247)
        return 'Z';

    return null;

}



function getArrayMax($arr,$field)
{
    foreach ($arr as $k=>$v){
        $temp[]=$v[$field];
    }
    return max($temp);
}