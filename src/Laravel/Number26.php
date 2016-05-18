<?php
/**
 * Number26 API
 * 
 * @author   André Daßler <mail@leuchte.net>
 * @license  http://opensource.org/licenses/MIT
 * @package  Number26
 */
namespace leuchte\Number26\Laravel;
use Illuminate\Support\Facades\Facade;
/**
 * Number26 facade
 */
class Number26 extends Facade
{
	public static function getFacadeAccessor()
	{
		return 'leuchte\Number26\Number26';
	}
}