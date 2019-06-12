Arhx ProxyChecker
==========================

Service for Proxy Checking that returns all the necessary information related to each proxy(s)

[Packagist]: <https://packagist.org/packages/arhx/proxy-checker>

## Installation

Type the following command in your project directory

`composer require arhx/proxy-checker`

## How to use

1. You should use the class `Arhx\ProxyChecker\ProxyChecker`
2. Pass `$url` & `$config` parameter in `ProxyChecker` class

```php
/*
*	$check_page [required]
*	$config [optional]
*/
    $check_page = 'http://myproxychecker.com/check.php';
    $config = [
        'timeout'   => 100,
        'check'     => ['get', 'post', 'cookie', 'referer', 'user_agent'],
    ];
    $checker = new ProxyChecker($check_page, $config);

/*
*	$proxy [required]
*	&$error_message [optional]
*
*/
$proxy = 'protocol://username:password@hostname:port';

$result = $checker->checkProxy($proxy, $error_message);

if($result){
    print_r($result);
}else{
    echo "Error: $error_message\n";
}
```

## Sample Output
 
 ```
Array
(
    [allowed] => Array
        (
            [0] => get
            [1] => post
            [2] => cookie
            [3] => referer
            [4] => user_agent
        )

    [disallowed] => Array
        (
        )

    [proxy_level] => elite
)

```

## Check page example
```php
<?php
// possible url for this file: http://myproxychecker.com/check.php
include 'vendor/autoload.php';
$checkResult = \Arhx\ProxyChecker\ProxyChecker::checkPage();
$checkResult = implode(PHP_EOL,$checkResult);
echo $checkResult;
```