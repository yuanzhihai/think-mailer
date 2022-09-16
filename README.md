# ThinkPHP6 邮件发送扩展

支持 `smtp` `sendmail` `log` 等驱动，其中`log`驱动会把邮件内容写入日志，供调试用

## 安装
~~~
composer require yzh52521/think-mailer
~~~

## 生成 Mailables#

在构建 Think 应用程序时，应用程序发送的每种类型的电子邮件都表示为一个 mailable 类。 
这些类存储在 app/mail 目录中。 如果您在应用程序中看不到此目录，请不要担心，因为它会在您使用 make:mail think 命令创建第一个可邮寄类时为您生成：

```
php think make:mail OrderShipped
```
## 编写 Mailables#
一旦你生成了一个 mailable 的类，打开它，这样我们就可以探索它的内容了。 首先，请注意所有可邮寄类的配置都是在 build 方法中完成的。 在此方法中，您可以调用各种方法，例如 from、subject、view 和 attach 来配置电子邮件的呈现和传递。

## 配置发件人#
使用 from 方法
首先，让我们浏览一下邮件的发件人的配置。或者，换句话说，邮件来自谁。有两种方法配置发件人。第一种，你可以在 mailable 类的 from 方法中使用 build 方法：

```php
/**
 * 构建消息
 *
 * @return $this
 */
public function build()
{
    return $this->from('example@example.com', 'Example')
                ->view('emails.orders.shipped');
}

```
## 使用全局 from 地址#
当然，如果你的应用在任何邮件中使用的「发件人」地址都一致的话，在你生成的每一个 mailable 类中调用 from 方法可能会很麻烦。因此，你可以在 config/mail.php 文件中指定一个全局的「发件人」地址。当某个 mailable 类没有指定「发件人」时，它将使用该全局「发件人」：

```php
'from' => ['address' => 'example@example.com', 'name' => 'App Name'],
```
此外，你可以在 config/mail.php 配置文件中定义一个全局的「回复」地址：
```php
'reply_to' => ['address' => 'example@example.com', 'name' => 'App Name'],
```
## 配置视图#

你可以在 mailable 类的 build 方法中使用 view 方法来指定在渲染邮件内容时要使用的模板。
由于每封邮件通常使用 think 模板 来渲染其内容，因此在构建邮件 HTML 内容时你可以使用 think 模板引擎提供的所有功能及享受其带来的便利性：

```php
/**
 * Build the message.
 *
 * @return $this
 */
public function build()
{
    return $this->view('emails.orders.shipped');
}
```
>技巧：你可以创建一个 view/emails 目录来存放你的所有邮件模板；当然，你也可以将其置于 view 目录下的任何位置。

## 纯文本邮件#
你可以使用 text 方法来定义一个纯文本格式的邮件。和 view 方法一样， 该 text 方法接受一个模板名，模板名指定了在渲染邮件内容时你想使用的模板。你既可以定义纯文本格式亦可定义 HTML 格式：
```php
/**
 * 构建消息.
 *
 * @return $this
 */
public function build()
{
    return $this->view('emails.orders.shipped')
                ->text('emails.orders.shipped_plain');
}

```
## 视图数据#
### 通过 Public 属性
通常情况下，你可能想要在渲染邮件的 HTML 内容时传递一些数据到视图中。有两种方法传递数据到视图中。第一种，你在 mailable 类中定义的所有 public 的属性都将自动传递到视图中。因此，举个例子，你可以将数据传递到你的 mailable 类的构造函数中，并将其设置为类的 public 属性：

```php
<?php

namespace app\mail;

use app\model\Order;
use think\queue\ShouldQueue;
use yzh52521\mail\Mailable;

class OrderShipped extends Mailable
{

    /**
     * 订单实例.
     *
     * @var \app\model\Order
     */
    public $order;

    /**
     * 创建一个消息实例.
     *
     * @param  \app\model\Order  $order
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * 构建消息.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.orders.shipped');
    }
}
```

当数据被设置成为 public 属性之后，它将被自动传递到你的视图中，因此你可以像您在 think 模板中那样访问它们：
```php
<div>
    Price: { $order->price }
</div>
```
### 通过 with 方法：
如果你想要在邮件数据发送到模板前自定义它们的格式，你可以使用 with 方法来手动传递数据到视图中。一般情况下，你还是需要通过 mailable 类的构造函数来传递数据；不过，你应该将它们定义为 protected 或 private 以防止它们被自动传递到视图中。然后，在您调用 with 方法的时候，你可以以数组的形式传递你想要传递给模板的数据：
```php
<?php

namespace app\mail;

use app\model\Order;
use think\queue\ShouldQueue;
use yzh52521\mail\Mailable;

class OrderShipped extends Mailable
{

    /**
     * 订单实例.
     *
     * @var \app\model\Order
     */
    protected $order;

    /**
     * 创建消息实例.
     *
     * @param  \app\model\Order  $order
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * 构建消息.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.orders.shipped')
                    ->with([
                        'orderName' => $this->order->name,
                        'orderPrice' => $this->order->price,
                    ]);
    }
}
```
当数据使用 with 法传递后，你便可以在视图中使用它们，此时，便可以像 think 模板的方式来访问它们：
```php
<div>
    Price: { $orderPrice }
</div>
```

### 附件
要在邮件中加入附件，在 build 方法中使用 attach 方法。 该 attach 方法接受文件的绝对路径作为它的第一个参数：
```php
/**
 * Build the message.
 *
 * @return $this
 */
public function build()
{
    return $this->view('emails.orders.shipped')
                ->attach('/path/to/file');
}
```
当附加文件到消息时，你也可以传递一个 array 给 attach 方法作为第二个参数，以指定显示名称和 / 或是 MIME 类型：
```php
/**
 * 构建消息.
 *
 * @return $this
 */
public function build()
{
    return $this->view('emails.orders.shipped')
                ->attach('/path/to/file', [
                    'name' => 'name.pdf',
                    'mime' => 'application/pdf',
                ]);
}
```
### 原始数据附件
该 attachData 可以使用字节数据作为附件。例如，你可以使用这个方法将内存中生成而没有保存到磁盘中的 PDF 附加到邮件中。 attachData 方法第一个参数接收原始字节数据，第二个参数为文件名，第三个参数接受一个数组以指定其他参数：
```php
/**
 * 构建消息.
 *
 * @return $this
 */
public function build()
{
    return $this->view('emails.orders.shipped')
                ->attachData($this->pdf, 'name.pdf', [
                    'mime' => 'application/pdf',
                ]);
}
```

## 自定义 Symfony 消息
该 Mailable 基类的 withSymfonyMessage 方法允许您注册一个闭包，在发送消息之前将使用 Symfony 消息实例调用该闭包。这使您有机会在消息传递之前对其进行深度自定义：
```php
use Symfony\Component\Mime\Email;

/**
 * 构建消息.
 *
 * @return $this
 */
public function build()
{
    $this->view('emails.orders.shipped');

    $this->withSymfonyMessage(function (Email $message) {
        $message->getHeaders()->addTextHeader(
            'Custom-Header', 'Header Value'
        );
    });

    return $this;
}
```
## 发送邮件#
要发送邮件，使用 Mail 门面 的方法。该 to 方法接受 邮件地址、用户实例或用户集合。如果传递一个对象或者对象集合，mailer 在设置收件人时将自动使用它们的 email 和 name 属性，因此请确保对象的这些属性可用。一旦指定了收件人，就可以将 mailable 类实例传递给 send 方法：
```php
<?php

namespace app\controller;

use app\mail\OrderShipped;
use app\model\Order;
use yzh52521\facade\Mail;

class OrderShipmentController 
{
    /**
     * 发送给定的订单.
     *
     * @param  \think\Request  $request
     */
    public function store(Request $request)
    {
        $order = Order::findOrFail($request->order_id);

        // Ship the order...

        Mail::send(new OrderShipped($order));
    }
}
```
在发送消息时不止可以指定收件人。还可以通过链式调用「to」、「cc」、「bcc」一次性指定抄送和密送收件人：

```php
Mail::to($request->user())
    ->cc($moreUsers)
    ->bcc($evenMoreUsers)
    ->send(new OrderShipped($order));
```
### 通过特定的 Mailer 发送邮件#
默认情况下，Think 将使用 mail 你的配置文件中配置为 default 邮件程序。 但是，你可以使用 mailer 方法通过特定的邮件程序配置发送：
```php
Mail::mailer('smtp')
        ->to($request->user())
        ->send(new OrderShipped($order));
```

