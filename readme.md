# N26 PHP API Wrapper

A simple wrapper to read several data from your N26 bank account.

## What is N26?

> N26 (formerly known as Number26) is Europe's first bank account developed entirely for smartphones. With your N26 bank account, MasterCard® and mobile app, you can conveniently transfer money from anywhere and keep track of your finances at all times. With MoneyBeam you're able to send money via sms or e-mail without the need to enter all the account details.


## Installation

	$ composer require leuchte/number26

## Usage

```php
use leuchte\Number26\Number26;

require __DIR__ . '/vendor/autoload.php';

$n26 = new Number26('email@number26.eu', 'yourPassword');

// Get recent 20 transactions
$transactions = $n26->getTransactions(['sort' => 'visibleTS', 'dir' => 'ASC', 'offset' => 0, 'limit' => 20]);

// Get a single transaction with the id $id
$transaction = $n26->getTransaction($id);

// Get infos about account, full true for all infos
$me = $n26->getMe($full = false);

// All spaces
$spaces = $n26->getSpaces();

// Space with id $id
$space = $n26->getSpace($id);

// All registered cards
$cards = $n26->getCards();

// Basic account information
$accounts = $n26->getAccounts();

// All saved addresses
$addresses = $n26->getAddresses();

// Address with id $id
$address = $n26->getAddress($id);

// Categories for transactions
$categories = $n26->getCategories();

// All transfer contacts
$contacts = $n26->getContacts();
