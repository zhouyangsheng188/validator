<?php
/**
 * Created by PhpStorm.
 * User: 周阳生
 * Date: 2019-4-3
 * Time: 16:50
 */
namespace zhouys;

class Validator
{
    // 实例
    protected static $instance;

    // 自定义的验证类型
    protected static $type = [];

    // 验证类型别名
    protected $alias = [
        '>' => 'gt', '>=' => 'egt', '<' => 'lt', '<=' => 'elt', '=' => 'eq', 'same' => 'eq',
    ];

    /**
     * @var array 语言数据
     */
    private static $lang = [];

    /**
     * @var string 语言作用域
     */
    private static $range = 'zh-cn';

    // 当前验证的规则
    protected $rule = [];

    // 验证提示信息
    protected $message = [];
    // 验证字段描述
    protected $field = [];

    // 验证规则默认提示信息
    protected static $typeMsg = [
        'require'     => ':attribute 必须填写',
        'number'      => ':attribute 必须是数字',
        'integer'     => ':attribute 必须是整型数字',
        'float'       => ':attribute 必须是浮点型',
        'boolean'     => ':attribute 必须是布尔值',
        'email'       => ':attribute 必须是一个有效的邮箱地址',
        'mobile'      => ':attribute 必须是有效的手机号码',
        'array'       => ':attribute 必须是一个数组',
        'date'        => ':attribute 必须是一个有效日期',
        'alpha'       => ':attribute 必须是字母',
        'alphaNum'    => ':attribute 只允许字母和数字',
        'alphaDash'   => ':attribute 只允许字母、数字和下划线，破折号',
        'chs'         => ':attribute 只允许汉字',
        'chsAlpha'    => ':attribute 只允许汉字、字母',
        'chsAlphaNum' => ':attribute 只允许汉字、字母和数字',
        'chsDash'     => ':attribute 只允许汉字、字母、数字和下划线_及破折号-',
        'url'         => ':attribute 必须是URL地址',
        'ip'          => ':attribute 必须是IP地址',
        'dateFormat'  => ':attribute 必须是 :rule 格式',
        'in'          => ':attribute 必须在 :rule 范围内',
        'notIn'       => ':attribute 不可在 :rule 中',
        'between'     => ':attribute 必须在 :1 ~ :2 范围内',
        'notBetween'  => ':attribute 不可在 :1 ~ :2 范围内',
        'length'      => ':attribute 的长度是 :rule',
        'max'         => ':attribute 的最大长度是:rule',
        'min'         => ':attribute 的最小长度是:rule',
        'confirm'     => ':attribute 和 :rule 不一致',
        'different'   => ':attribute 不能和 :rule 一样',
        'egt'         => ':attribute 必须大于等于 :rule',
        'gt'          => ':attribute 必须大于 :rule',
        'elt'         => ':attribute 必须小于等于 :rule',
        'lt'          => ':attribute 必须小于 :rule',
        'eq'          => ':attribute 必须等于 :rule',
        'regex'       => ':attribute not conform to the rules'
    ];

    // 当前验证场景
    protected $currentScene = null;

    // 正则表达式 regex = ['zip'=>'\d{6}',...]
    protected $regex = [];

    // 验证场景 scene = ['edit'=>'name1,name2,...']
    protected $scene = [];

    // 验证失败错误信息
    protected $error = [];

    // 批量验证
    protected $batch = false;

    /**
     * 构造函数
     * @access public
     * @param array $rules 验证规则
     * @param array $message 验证提示信息
     * @param array $field 验证字段描述信息
     */
    public function __construct(array $rules = [], $message = [], $field = [])
    {
        $this->rule = array_merge($this->rule, $rules);
        $this->message = array_merge($this->message, $message);
        $this->field = array_merge($this->field, $field);
    }

    /**
     * 数据自动验证
     * @access public
     * @param array     $data  数据
     * @param mixed     $rules  验证规则
     * @param string    $scene 验证场景
     * @return bool
     */
    public function check($data, $rules = [], $scene = '')
    {
        $this->error = [];

        if (empty($rules)) {
            // 读取验证规则
            $rules = $this->rule;
        }

        // 分析验证规则
        $scene = $this->getScene($scene);
        if (is_array($scene)) {
            // 处理场景验证字段
            $change = [];
            $array  = [];
            foreach ($scene as $k => $val) {
                if (is_numeric($k)) {
                    $array[] = $val;
                } else {
                    $array[]    = $k;
                    $change[$k] = $val;
                }
            }
        }

        foreach ($rules as $key => $item) {
            // field => rule1|rule2... field=>['rule1','rule2',...]
            if (is_numeric($key)) {
                // [field,rule1|rule2,msg1|msg2]
                $key  = $item[0];
                $rule = $item[1];
                if (isset($item[2])) {
                    $msg = is_string($item[2]) ? explode('|', $item[2]) : $item[2];
                } else {
                    $msg = [];
                }
            } else {
                $rule = $item;
                $msg  = [];
            }
            if (strpos($key, '|')) {
                // 字段|描述 用于指定属性名称
                list($key, $title) = explode('|', $key);
            } else {
                $title = isset($this->field[$key]) ? $this->field[$key] : $key;
            }

            // 场景检测
            if (!empty($scene)) {
                if ($scene instanceof \Closure && !call_user_func_array($scene, [$key, $data])) {
                    continue;
                } elseif (is_array($scene)) {
                    if (!in_array($key, $array)) {
                        continue;
                    } elseif (isset($change[$key])) {
                        // 重载某个验证规则
                        $rule = $change[$key];
                    }
                }
            }

            // 获取数据 支持二维数组
            $value = $this->getDataValue($data, $key);

            // 字段验证
            if ($rule instanceof \Closure) {
                // 匿名函数验证 支持传入当前字段和所有字段两个数据
                $result = call_user_func_array($rule, [$value, $data]);
            } else {
                $result = $this->checkItem($key, $value, $rule, $data, $title, $msg);
            }
            if (true !== $result) {
                // 没有返回true 则表示验证失败
                if (!empty($this->batch)) {
                    // 批量验证
                    if (is_array($result)) {
                        $this->error = array_merge($this->error, $result);
                    } else {
                        $this->error[$key] = $result;
                    }
                } else {
                    $this->error = $result;
                    return false;
                }
            }
        }
        return !empty($this->error) ? false : true;
    }

    /**
     * 获取数据验证的场景
     * @access protected
     * @param string $scene  验证场景
     * @return array
     */
    protected function getScene($scene = '')
    {
        if (empty($scene)) {
            // 读取指定场景
            $scene = $this->currentScene;
        }

        if (!empty($scene) && isset($this->scene[$scene])) {
            // 如果设置了验证适用场景
            $scene = $this->scene[$scene];
            if (is_string($scene)) {
                $scene = explode(',', $scene);
            }
        } else {
            $scene = [];
        }
        return $scene;
    }

    /**
     * 获取数据值
     * @access protected
     * @param array $data 数据
     * @param string $key 数据标识 支持二维
     * @return mixed
     */
    protected function getDataValue($data, $key)
    {
        if (is_numeric($key)) {
            $value = $key;
        } elseif (strpos($key, '.')) {
            // 支持二维数组验证
            list($name1, $name2) = explode('.', $key);
            $value               = isset($data[$name1][$name2]) ? $data[$name1][$name2] : null;
        } else {
            $value = isset($data[$key]) ? $data[$key] : null;
        }
        return $value;
    }

    /**
     * 验证单个字段规则
     * @access protected
     * @param string    $field  字段名
     * @param mixed     $value  字段值
     * @param mixed     $rules  验证规则
     * @param array     $data  数据
     * @param string    $title  字段描述
     * @param array     $msg  提示信息
     * @return mixed
     */
    protected function checkItem($field, $value, $rules, $data, $title = '', $msg = [])
    {
        // 支持多规则验证 require|in:a,b,c|... 或者 ['require','in'=>'a,b,c',...]
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }
        $i = 0;
        foreach ($rules as $key => $rule) {
            if ($rule instanceof \Closure) {
                $result = call_user_func_array($rule, [$value, $data]);
                $info   = is_numeric($key) ? '' : $key;
            } else {
                // 判断验证类型
                list($type, $rule, $info) = $this->getValidateType($key, $rule);
                // 如果不是require 有数据才会行验证
                if (0 === strpos($info, 'require') || (!is_null($value) && '' !== $value)) {
                    // 验证类型
                    $callback = isset(self::$type[$type]) ? self::$type[$type] : [$this, $type];
                    // 验证数据
                    $result = call_user_func_array($callback, [$value, $rule, $data, $field, $title]);
                } else {
                    $result = true;
                }
            }
            if (false === $result) {
                // 验证失败 返回错误信息
                if (isset($msg[$i])) {
                    $message = $msg[$i];
                    if (is_string($message) && strpos($message, '{%') === 0) {
                        $message = $this->long_get(substr($message, 2, -1));
                    }
                } else {
                    $message = $this->getRuleMsg($field, $title, $info, $rule);
                }
                return $message;
            } elseif (true !== $result) {
                // 返回自定义错误信息
                if (is_string($result) && false !== strpos($result, ':')) {
                    $result = str_replace([':attribute', ':rule'], [$title, (string) $rule], $result);
                }
                return $result;
            }
            $i++;
        }
        return $result;
    }

    /**
     * 获取当前验证类型及规则
     * @access public
     * @param  mixed     $key
     * @param  mixed     $rule
     * @return array
     */
    protected function getValidateType($key, $rule)
    {
        // 判断验证类型
        if (!is_numeric($key)) {
            return [$key, $rule, $key];
        }

        if (strpos($rule, ':')) {
            list($type, $rule) = explode(':', $rule, 2);
            if (isset($this->alias[$type])) {
                // 判断别名
                $type = $this->alias[$type];
            }
            $info = $type;
        } elseif (method_exists($this, $rule)) {
            $type = $rule;
            $info = $rule;
            $rule = '';
        } else {
            $type = 'is';
            $info = $rule;
        }
        return [$type, $rule, $info];
    }

    /**
     * 获取语言定义(不区分大小写)
     * @access public
     * @param  string|null $name  语言变量
     * @param  array       $vars  变量替换
     * @param  string      $range 语言作用域
     * @return mixed
     */
    public function long_get($name = null, $vars = [], $range = '')
    {
        $range = $range ?: self::$range;

        // 空参数返回所有定义
        if (empty($name)) {
            return self::$lang[$range];
        }

        $key   = strtolower($name);
        $value = isset(self::$lang[$range][$key]) ? self::$lang[$range][$key] : $name;

        // 变量解析
        if (!empty($vars) && is_array($vars)) {
            /**
             * Notes:
             * 为了检测的方便，数字索引的判断仅仅是参数数组的第一个元素的key为数字0
             * 数字索引采用的是系统的 sprintf 函数替换，用法请参考 sprintf 函数
             */
            if (key($vars) === 0) {
                // 数字索引解析
                array_unshift($vars, $value);
                $value = call_user_func_array('sprintf', $vars);
            } else {
                // 关联索引解析
                $replace = array_keys($vars);
                foreach ($replace as &$v) {
                    $v = "{:{$v}}";
                }
                $value = str_replace($replace, $vars, $value);
            }

        }
        return $value;
    }

    /**
     * 获取验证规则的错误提示信息
     * @access protected
     * @param string    $attribute  字段英文名
     * @param string    $title  字段描述名
     * @param string    $type  验证规则名称
     * @param mixed     $rule  验证规则数据
     * @return string
     */
    protected function getRuleMsg($attribute, $title, $type, $rule)
    {
        if (isset($this->message[$attribute . '.' . $type])) {
            $msg = $this->message[$attribute . '.' . $type];
        } elseif (isset($this->message[$attribute][$type])) {
            $msg = $this->message[$attribute][$type];
        } elseif (isset($this->message[$attribute])) {
            $msg = $this->message[$attribute];
        } elseif (isset(self::$typeMsg[$type])) {
            $msg = self::$typeMsg[$type];
        } elseif (0 === strpos($type, 'require')) {
            $msg = self::$typeMsg['require'];
        } else {
            $msg = $title . $this->long_get('not conform to the rules');
        }

        if (is_string($msg) && 0 === strpos($msg, '{%')) {
            $msg = $this->long_get(substr($msg, 2, -1));
        } elseif ($this->long_has($msg)) {
            $msg = $this->long_get($msg);
        }

        if (is_string($msg) && is_scalar($rule) && false !== strpos($msg, ':')) {
            // 变量替换
            if (is_string($rule) && strpos($rule, ',')) {
                $array = array_pad(explode(',', $rule), 3, '');
            } else {
                $array = array_pad([], 3, '');
            }
            $msg = str_replace(
                [':attribute', ':rule', ':1', ':2', ':3'],
                [$title, (string) $rule, $array[0], $array[1], $array[2]],
                $msg);
        }
        return $msg;
    }

    /**
     * 获取语言定义(不区分大小写)
     * @access public
     * @param  string|null $name  语言变量
     * @param  string      $range 语言作用域
     * @return mixed
     */
    public function long_has($name, $range = '')
    {
        $range = $range ?: self::$range;

        return isset(self::$lang[$range][strtolower($name)]);
    }

    /**
     * 设置验证场景
     * @access public
     * @param string|array  $name  场景名或者场景设置数组
     * @param mixed         $fields 要验证的字段
     * @return Validate
     */
    public function scene($name, $fields = null)
    {
        if (is_array($name)) {
            $this->scene = array_merge($this->scene, $name);
        }if (is_null($fields)) {
        // 设置当前场景
        $this->currentScene = $name;
    } else {
        // 设置验证场景
        $this->scene[$name] = $fields;
    }
        return $this;
    }

    /**
     * 设置批量验证
     * @access public
     * @param bool $batch  是否批量验证
     * @return Validate
     */
    public function batch($batch = true)
    {
        $this->batch = $batch;
        return $this;
    }

    /**
     * 验证字段值是否为有效格式
     * @access protected
     * @param mixed     $value  字段值
     * @param string    $rule  验证规则
     * @param array     $data  验证数据
     * @return bool
     */
    protected function is($value, $rule, $data = [])
    {
        switch ($rule) {
            case 'require':
                // 必须
                $result = !empty($value) || '0' == $value;
                break;
            case 'date':
                // 是否是一个有效日期
                $result = false !== strtotime($value);
                break;
            case 'alpha':
                // 只允许字母
                $result = $this->regex($value, '/^[A-Za-z]+$/');
                break;
            case 'alphaNum':
                // 只允许字母和数字
                $result = $this->regex($value, '/^[A-Za-z0-9]+$/');
                break;
            case 'alphaDash':
                // 只允许字母、数字和下划线 破折号
                $result = $this->regex($value, '/^[A-Za-z0-9\-\_]+$/');
                break;
            case 'chs':
                // 只允许汉字
                $result = $this->regex($value, '/^[\x{4e00}-\x{9fa5}]+$/u');
                break;
            case 'chsAlpha':
                // 只允许汉字、字母
                $result = $this->regex($value, '/^[\x{4e00}-\x{9fa5}a-zA-Z]+$/u');
                break;
            case 'chsAlphaNum':
                // 只允许汉字、字母和数字
                $result = $this->regex($value, '/^[\x{4e00}-\x{9fa5}a-zA-Z0-9]+$/u');
                break;
            case 'chsDash':
                // 只允许汉字、字母、数字和下划线_及破折号-
                $result = $this->regex($value, '/^[\x{4e00}-\x{9fa5}a-zA-Z0-9\_\-]+$/u');
                break;
            case 'activeUrl':
                // 是否为有效的网址
                $result = checkdnsrr($value);
                break;
            case 'ip':
                // 是否为IP地址
                $result = $this->filter($value, [FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6]);
                break;
            case 'url':
                // 是否为一个URL地址
                $result = $this->filter($value, FILTER_VALIDATE_URL);
                break;
            case 'float':
                // 是否为float
                $result = $this->filter($value, FILTER_VALIDATE_FLOAT);
                break;
            case 'number':
                $result = is_numeric($value);
                break;
            case 'integer':
                // 是否为整型
                $result = $this->filter($value, FILTER_VALIDATE_INT);
                break;
            case 'email':
                // 是否为邮箱地址
                $result = $this->filter($value, FILTER_VALIDATE_EMAIL);
                break;
            case 'boolean':
                // 是否为布尔值
                $result = in_array($value, [true, false, 0, 1, '0', '1']);
                break;
            case 'array':
                // 是否为数组
                $result = is_array($value);
                break;
            default:
                if (isset(self::$type[$rule])) {
                    // 注册的验证规则
                    $result = call_user_func_array(self::$type[$rule], [$value]);
                } else {
                    // 正则验证
                    $result = $this->regex($value, $rule);
                }
        }
        return $result;
    }

    /**
     * 使用filter_var方式验证
     * @access protected
     * @param mixed     $value  字段值
     * @param mixed     $rule  验证规则
     * @return bool
     */
    protected function filter($value, $rule)
    {
        if (is_string($rule) && strpos($rule, ',')) {
            list($rule, $param) = explode(',', $rule);
        } elseif (is_array($rule)) {
            $param = isset($rule[1]) ? $rule[1] : null;
            $rule  = $rule[0];
        } else {
            $param = null;
        }
        return false !== filter_var($value, is_int($rule) ? $rule : filter_id($rule), $param);
    }

    /**
     * 验证时间和日期是否符合指定格式
     * @access public  例：dateFormat:Y-m-d H:i:s
     * @param  mixed     $value  字段值
     * @param  mixed     $rule  验证规则
     * @return bool
     */
    public function dateFormat($value, $rule)
    {
        $info = date_parse_from_format($rule, $value);
        return 0 == $info['warning_count'] && 0 == $info['error_count'];
    }

    /**
     * 验证是否在范围内
     * @access public
     * @param  mixed     $value  字段值
     * @param  mixed     $rule  验证规则
     * @return bool
     */
    public function in($value, $rule)
    {
        return in_array($value, is_array($rule) ? $rule : explode(',', $rule));
    }

    /**
     * 验证是否不在某个范围
     * @access public
     * @param  mixed     $value  字段值
     * @param  mixed     $rule  验证规则
     * @return bool
     */
    public function notIn($value, $rule)
    {
        return !in_array($value, is_array($rule) ? $rule : explode(',', $rule));
    }

    /**
     * between验证数据
     * @access public
     * @param  mixed     $value  字段值
     * @param  mixed     $rule  验证规则
     * @return bool
     */
    public function between($value, $rule)
    {
        if (is_string($rule)) {
            $rule = explode(',', $rule);
        }
        list($min, $max) = $rule;

        return $value >= $min && $value <= $max;
    }

    /**
     * 使用notbetween验证数据
     * @access public
     * @param  mixed     $value  字段值
     * @param  mixed     $rule  验证规则
     * @return bool
     */
    public function notBetween($value, $rule)
    {
        if (is_string($rule)) {
            $rule = explode(',', $rule);
        }
        list($min, $max) = $rule;

        return $value < $min || $value > $max;
    }

    /**
     * 验证数据长度
     * @access public
     * @param  mixed     $value  字段值
     * @param  mixed     $rule  验证规则
     * @return bool
     */
    public function length($value, $rule)
    {
        if (is_array($value)) {
            $length = count($value);
        } else {
            $length = mb_strlen((string) $value);
        }

        if (strpos($rule, '~')) {
            // 长度区间
            list($min, $max) = explode('~', $rule);
            return $length >= $min && $length <= $max;
        }

        // 指定长度
        return $length == $rule;
    }

    /**
     * 使用正则验证数据
     * @access protected
     * @param mixed     $value  字段值
     * @param mixed     $rule  验证规则 正则规则或者预定义正则名
     * @return mixed
     */
    protected function regex($value, $rule)
    {
        if (isset($this->regex[$rule])) {
            $rule = $this->regex[$rule];
        }
        if (0 !== strpos($rule, '/') && !preg_match('/\/[imsU]{0,4}$/', $rule)) {
            // 不是正则表达式则两端补上/
            $rule = '/^' . $rule . '$/';
        }
        return 1 === preg_match($rule, (string) $value);
    }

    /**
     * 验证数据最小长度
     * @access public
     * @param  mixed     $value  字段值
     * @param  mixed     $rule  验证规则
     * @return bool
     */
    public function min($value, $rule)
    {
        if (is_array($value)) {
            $length = count($value);
        } else {
            $length = mb_strlen((string) $value);
        }

        return $length >= $rule;
    }

    /**
     * 验证数据最大长度
     * @access public
     * @param  mixed     $value  字段值
     * @param  mixed     $rule  验证规则
     * @return bool
     */
    public function max($value, $rule)
    {
        if (is_array($value)) {
            $length = count($value);
        } else {
            $length = mb_strlen((string) $value);
        }

        return $length <= $rule;
    }

    /**
     * 验证是否和某个字段的值一致 confirm:pass
     * @access public
     * @param  mixed     $value 字段值
     * @param  mixed     $rule  验证规则
     * @param  array     $data  数据
     * @param  string    $field 字段名
     * @return bool
     */
    public function confirm($value, $rule, $data = [], $field = '')
    {
        if ('' == $rule) {
            if (strpos($field, '_confirm')) {
                $rule = strstr($field, '_confirm', true);
            } else {
                $rule = $field . '_confirm';
            }
        }

        return $this->getDataValue($data, $rule) === $value;
    }

    /**
     * 验证是否和某个字段的值是否不同
     * @access public
     * @param  mixed $value 字段值
     * @param  mixed $rule  验证规则
     * @param  array $data  数据
     * @return bool
     */
    public function different($value, $rule, $data = [])
    {
        return $this->getDataValue($data, $rule) != $value;
    }

    /**
     * 验证是否大于等于某个值
     * @access public
     * @param  mixed     $value  字段值
     * @param  mixed     $rule  验证规则
     * @param  array     $data  数据
     * @return bool
     */
    public function egt($value, $rule, $data = [])
    {
        return $value >= $this->getDataValue($data, $rule);
    }

    /**
     * 验证是否大于某个值
     * @access public
     * @param  mixed     $value  字段值
     * @param  mixed     $rule  验证规则
     * @param  array     $data  数据
     * @return bool
     */
    public function gt($value, $rule, $data)
    {
        return $value > $this->getDataValue($data, $rule);
    }

    /**
     * 验证是否小于等于某个值
     * @access public
     * @param  mixed     $value  字段值
     * @param  mixed     $rule  验证规则
     * @param  array     $data  数据
     * @return bool
     */
    public function elt($value, $rule, $data = [])
    {
        return $value <= $this->getDataValue($data, $rule);
    }

    /**
     * 验证是否小于某个值
     * @access public
     * @param  mixed     $value  字段值
     * @param  mixed     $rule  验证规则
     * @param  array     $data  数据
     * @return bool
     */
    public function lt($value, $rule, $data = [])
    {
        return $value < $this->getDataValue($data, $rule);
    }

    /**
     * 验证是否等于某个值
     * @access public
     * @param  mixed     $value  字段值
     * @param  mixed     $rule  验证规则
     * @return bool
     */
    public function eq($value, $rule)
    {
        return $value == $rule;
    }

    /**场景验证
     * @param $v 、场景名称
     * @param $params 、需要验证的参数
     * @return bool
     */
    public function sceneCheck($v,$params)
    {
        $result=$this->scene($v)->check($params);
        if(!$result){
            $error=$this->getError();
            $this->returnError($error);
            return false;
        }else{
            return true;
        }
    }

    // 获取错误信息
    public function getError()
    {
        return $this->error;
    }

    public function returnError($message, $code='0x000001')
    {
        header('Content-Type:application/json; charset=utf-8');
        $result=array(
            'message'=>$message,
            'code'=>$code,
        );
        echo json_encode($result);exit;
    }

    //检测是否是正整数
    protected function isInteger($value)
    {
        if(is_numeric($value) && is_int($value + 0) && ($value + 0)>0){
            return true;
        }else{
            return false;
        }
    }

    //检测是否为空
    protected function isNotEmpty($value)
    {
        $data = trim($value);
        if(empty($data)){
            return false;
        }else{
            return true;
        }
    }

    //检测手机号
    public function mobile($value)
    {
        $rule='/^\d{11}$/';
        $result=preg_match($rule,$value);
        if($result){
            return true;
        }else{
            return false;
        }
    }

}
