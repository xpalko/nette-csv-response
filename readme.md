CSV Response
=============

Install:
```bat
composer require xsuchy09/nette-csv-response
```

Use:

```php
class SomePresenter extends BasePresenter
{
	public function actionDefault()
	{
		$data = [
			[ 'name' => 'George', 'age' => 15, 'grade' => 2, ],
			[ 'name' => 'Jack', 'age' => 17, 'grade' => 4, ],
			[ 'name' => 'Mary', 'age' => 17, 'grade' => 1, ],
		];

		$response = new \XSuchy09\Application\Responses\CsvResponse($data, 'students.csv');
		$this->sendResponse( $response );
	}
}
```

Individual settings example:

```php
use \XSuchy09\Application\Responses\CsvResponse;

// $response is instance of \XSuchy09\Application\Responses\CsvResponse
$response
	->setDelimiter(CsvResponse::SEMICOLON)
	->setEnclosure('"') // this is default value so not require to call when set to "
	->setEscapeChar('\\') // this is default value so not require to call when se to \
	->setOutputCharset('utf-8')
	->setContentType('application/csv')
	->setHeadingFormatter('mb_strtoupper')
	->setDataFormatter('trim')
;
```
