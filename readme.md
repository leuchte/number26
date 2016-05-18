# Number26 PHP API Wrapper

## What is Number26?

> NUMBER26 is Europe's first bank account developed entirely for smartphones. With your NUMBER26 bank account, MasterCard® and mobile app, you can conveniently transfer money from anywhere and keep track of your finances at all times. With MoneyBeam you're able to send money via sms or e-mail without the need to enter all the account details.
>
> There are no costs or fees, which means you can withdraw money at any ATM worldwide, free of charge. No ATM around you? Just use CASH26 to withdraw and deposit cash at your supermarket.


## Installation

	$ composer require leuchte/number26

## Usage

```php
$n26 = new leuchte\Number26\Number26;

// Get transaction 
$transactions = $n26->getTransactions(['sort' => 'visibleTS', 'dir' => 'ASC', 'offset' => 0, 'limit' => 200]);

// Create transaction
$n26->makeTransfer('100.01', '0000', 'BICXXXXX', 'DE001111111111111111', 'Your name', 'a reference text');