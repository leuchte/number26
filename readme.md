# N26 PHP API Wrapper

## What is N26?

> N26 (formerly known as Number26) is Europe's first bank account developed entirely for smartphones. With your N26 bank account, MasterCard® and mobile app, you can conveniently transfer money from anywhere and keep track of your finances at all times. With MoneyBeam you're able to send money via sms or e-mail without the need to enter all the account details.


## Installation

	$ composer require leuchte/number26

## Usage

```php
use leuchte\Number26\Number26;

require __DIR__ . '/vendor/autoload.php';

$n26 = new Number26('email@number26.eu', 'yourPassword');

// Get transactions
$transactions = $n26->getTransactions(['sort' => 'visibleTS', 'dir' => 'ASC', 'offset' => 0, 'limit' => 200]);

// Create transaction
$n26->makeTransfer('100.01', '0000', 'BICXXXXX', 'DE001111111111111111', 'Your name', 'a reference text');