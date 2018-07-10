<?php
/******************************************************************************
 * Author: Petr Suchy (xsuchy09) <suchy@wamos.cz> <http://www.wamos.cz>
 * Subject: WAMOS <http://www.wamos.cz>
 * Project: nette-csv-response
 * Copyright: (c) Petr Suchy (xsuchy09) <suchy@wamos.cz> <http://www.wamos.cz>
 *****************************************************************************/

namespace XSuchy09\Application\Responses;

use InvalidArgumentException;
use LogicException;
use Nette;
use Traversable;

/**
 * CSV download response.
 *
 * @package XSuchy09\Application\Responses
 */
class CsvResponse implements Nette\Application\IResponse
{
	
	use Nette\SmartObject;

	/** standard glues */
	const COMMA = ',',
			SEMICOLON = ';',
			TAB = ' ';

	/**
	 * @var bool
	 */
	protected $addHeading;

	/**
	 * @var string
	 */
	protected $glue = self::COMMA;

	/**
	 * @var string
	 */
	protected $outputCharset = 'utf-8';

	/**
	 * @var string
	 */
	protected $contentType = 'text/csv';

	/**
	 * @var callable
	 */
	protected $headingFormatter = 'self::firstUpperNoUnderscoresFormatter';

	/**
	 * @var callable
	 */
	protected $dataFormatter;

	/**
	 * @var array
	 */
	protected $data;

	/**
	 * @var string
	 */
	protected $filename;

	/**
	 * In accordance with Nette Framework accepts only UTF-8 input. For output @see setOutputCharset().
	 *
	 * @param type $data
	 * @param string $filename
	 * @param bool $addHeading Whether add first row from data array keys (keys are taken from first row)
	 * 
	 * @throws InvalidArgumentException
	 */
	public function __construct($data, string $filename = 'output.csv', bool $addHeading = true)
	{
		if ($data instanceof Traversable) {
			$data = iterator_to_array($data);
		}
		if (!is_array($data)) {
			throw new InvalidArgumentException(sprintf('%s: data must be two dimensional array or instance of Traversable.', __CLASS__));
		}
		$this->data = array_values($data);
		$this->filename = $filename;
		$this->addHeading = $addHeading;
	}

	/**
	 * Set value separator.
	 *
	 * @param string $glue
	 * 
	 * @return CsvResponse
	 * @throws InvalidArgumentException
	 */
	public function setGlue(string $glue): CsvResponse
	{
		if (empty($glue) || preg_match('/[\n\r"]/s', $glue)) {
			throw new InvalidArgumentException(sprintf('%s: glue cannot be an empty or reserved character.', __CLASS__));
		}
		$this->glue = $glue;
		return $this;
	}

	/**
	 * Set charset of response.
	 * 
	 * @param string $charset
	 * 
	 * @return CsvResponse
	 */
	public function setOutputCharset(string $charset): CsvResponse
	{
		$this->outputCharset = $charset;
		return $this;
	}

	/**
	 * Set content type of response.
	 *
	 * @param string $contentType
	 * 
	 * @return CsvResponse
	 */
	public function setContentType(string $contentType): CsvResponse
	{
		$this->contentType = $contentType;
		return $this;
	}

	/**
	 * When heading added, it is formatted by given callback.
	 * Default @see firstUpperNoUnderscoresFormatter(); erase it by calling setHeadingFormatter(null).
	 *
	 * @param callable|null $formatter
	 * 
	 * @return CsvResponse
	 * @throws InvalidArgumentException
	 */
	public function setHeadingFormatter(?callable $formatter): CsvResponse
	{
		if ($formatter !== null && !is_callable($formatter)) {
			throw new InvalidArgumentException(sprintf('%s: heading formatter must be callable.', __CLASS__));
		}
		$this->headingFormatter = $formatter;
		return $this;
	}

	/**
	 * If given, every value is formatted by given callback.
	 *
	 * @param callable|null $formatter
	 * 
	 * @return CsvResponse
	 * @throws InvalidArgumentException
	 */
	public function setDataFormatter(?callable $formatter): CsvResponse
	{
		if ($formatter !== null && !is_callable($formatter)) {
			throw new InvalidArgumentException(sprintf(': data formatter must be callable.', __CLASS__));
		}
		$this->dataFormatter = $formatter;
		return $this;
	}

	/**
	 * Heading formatted.
	 * 
	 * @param string $heading
	 * 
	 * @return string
	 */
	public static function firstUpperNoUnderscoresFormatter(string $heading): string
	{
		$heading = str_replace('_', ' ', $heading);
		$heading = mb_strtoupper(mb_substr($heading, 0, 1)) . mb_substr($heading, 1);
		return $heading;
	}

	/**
	 * Sends response to output.
	 *
	 * @param Nette\Http\IRequest $httpRequest
	 * @param Nette\Http\IResponse $httpResponse
	 */
	public function send(Nette\Http\IRequest $httpRequest, Nette\Http\IResponse $httpResponse)
	{
		$httpResponse->setContentType($this->contentType, $this->outputCharset);
		$attachment = 'attachment';
		if (!empty($this->filename)) {
			$attachment .= sprintf('; filename="%s"', $this->filename);
		}
		$httpResponse->setHeader('Content-Disposition', $attachment);
		$data = $this->formatCsv();
		$httpResponse->setHeader('Content-Length', strlen($data));
		print $data;
	}

	/**
	 * Format CSV.
	 * 
	 * @return string
	 * @throws InvalidArgumentException
	 * @throws LogicException
	 */
	protected function formatCsv(): string
	{
		if (empty($this->data)) {
			return '';
		}
		ob_start();
		$buffer = fopen("php://output", 'w');
		// if output charset is not UTF-8
		$recode = strcasecmp($this->outputCharset, 'utf-8');
		foreach ($this->data as $n => $row) {
			if ($row instanceof Traversable) {
				$row = iterator_to_array($row);
			}
			if (!is_array($row)) {
				throw new InvalidArgumentException(sprintf('%s: row "%d" must be array or instance of Traversable, "%s" given.', __CLASS__, $n, gettype($row)));
			}
			if ($n === 0 && $this->addHeading) {
				$labels = array_keys($row);
				if ($this->headingFormatter || $recode) {
					foreach ($labels as &$label) {
						if ($this->headingFormatter) {
							$label = call_user_func(
									$this->headingFormatter, $label
							);
						}
						if ($recode) {
							$label = iconv('utf-8', $this->outputCharset . '//TRANSLIT', $label);
						}
					}
				}
				fputcsv($buffer, $labels, $this->glue);
			}
			if ($this->dataFormatter || $recode) {
				foreach ($row as &$value) {
					if ($this->dataFormatter) {
						$value = call_user_func($this->dataFormatter, $value);
					}
					if ($recode) {
						$value = iconv('utf-8', $this->outputCharset . '//TRANSLIT', $value);
					}
				}
			}
			fputcsv($buffer, $row, $this->glue);
		}
		fclose($buffer);
		$return = ob_get_clean();
		if ($return === false) {
			throw new LogicException('Output buffering is not active.');
		}
		return $return;
	}

}
