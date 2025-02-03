# N26 PHP API Wrapper

A simple wrapper to read several data from your N26 bank account.

## What is N26?

> N26 (formerly known as Number26) is Europe's first bank account developed entirely for smartphones. With your N26 bank account, MasterCard® and mobile app, you can conveniently transfer money from anywhere and keep track of your finances at all times. With MoneyBeam you're able to send money via sms or e-mail without the need to enter all the account details.


## Disclosure

Please note that N26 does not provide official documentation for this API interface. Changes are not documented and functions can be removed at any time.


## Installation

	$ composer require leuchte/number26

## Usage

```php
use leuchte\Number26\Number26;

require __DIR__ . '/vendor/autoload.php';

$n26 = new Number26('myemail@mydomain.com', 'yourPassword');

// Get recent 20 transactions
/**
 * Possible params:
 * textFilter - Filter by a text
 * limit - Limit the results
 * lastId - The last received id. Next batch starts with the id after the lastId
 * from - Timestamp in miliseconds from where to start
 * to - Timestamp in miliseconds where to end
 */ 
$transactions = $n26->getTransactions(['limit' => 20]);

// Get a single transaction with the id $id
$transaction = $n26->getTransaction($id);

// Get infos about account
$me = $n26->getMe();

// All registered cards
$cards = $n26->getCards();

// Basic account information
$accounts = $n26->getAccounts();

// All saved addresses
$addresses = $n26->getAddresses();

// Categories for transactions
$categories = $n26->getCategories();

// All features for a country. Germany (DEU) in this case
$countryFeatures = $n26->getCountryFeatures('DEU');

// Get api version informations
$version = $n26->getVersion();

// Get a list of all statements
$statements = $n26->getStatements();

// Get a specific statement as pdf
$statement = $n26->getStatement('statement-2024-12');

// Get a csv file with all transactions
$n26->setCsvFilename('n26_transactions')->getCsv();

