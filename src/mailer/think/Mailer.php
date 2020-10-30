<?php
/**
 * think-mailer [A powerful and beautiful php mailer for All of ThinkPHP and Other PHP Framework based SwiftMailer]
 *
 * @author    yzh52521
 * @link      https://github.com/yzh52521/think-mailer
 * @copyright 2020 yzh52521 all rights reserved.
 * @license   http://www.apache.org/licenses/LICENSE-2.0
 */

namespace mailer\think;

use think\facade\View;
use think\facade\Config as ThinkConfig;

/**
 * Class Mailer
 * @package mailer\think
 */
class Mailer extends \mailer\lib\Mailer
{
    /**
     * 载入一个模板作为邮件内容
     *
     * @param string $template
     * @param array $param
     * @param array $config
     *
     * @return Mailer
     */
    public function view($template, $param = [], $config = [])
    {
        if (strpos(\think\App::VERSION, '6.0') !== false) {
            $view = View::instance(ThinkConfig::get('view'), ThinkConfig::get('tpl_replace_string'));
        } else {
            $view = View::instance(ThinkConfig::get('template'), ThinkConfig::get('tpl_replace_string'));
        }
        // 处理变量中包含有对元数据嵌入的变量
        foreach ($param as $k => $v) {
            $this->embedImage($k, $v, $param);
        }
        $content = $view->fetch($template, $param, [], $config);

        return $this->html($content);
    }
}
