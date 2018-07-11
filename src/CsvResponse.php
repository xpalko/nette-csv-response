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

	/**
	 * standard delimiters
	 */
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
	protected $delimiter = self::COMMA;
	
	/**
	 * @var string
	 */
	protected $enclosure = '"';
	
	/**
	 * @var string
	 */
	protected $escapeChar = '\\';

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
		if (true === $data instanceof Traversable) {
			$data = iterator_to_array($data);
		}
		if (false === is_array($data)) {
			throw new InvalidArgumentException(sprintf('%s: data must be two dimensional array or instance of Traversable.', __CLASS__));
		}
		$this->data = array_values($data);
		$this->filename = $filename;
		$this->addHeading = $addHeading;
	}
	
	/**
	 * Set value separator. Deprecated, use setDelimiter instead. Just for backward compatibility.
	 * 
	 * @deprecated since version 0.2.0
	 * 
	 * @param string $glue
	 * 
	 * @return CsvResponse
	 */
	public function setGlue(string $glue): CsvResponse
	{
		return $this->setDelimiter($glue);
	}

	/**
	 * Set value separator.
	 *
	 * @param string $delimiter
	 * 
	 * @return CsvResponse
	 * @throws InvalidArgumentException
	 */
	public function setDelimiter(string $delimiter): CsvResponse
	{
		if (true === empty($delimiter) || preg_match('/[\n\r]/s', $delimiter) === 1 || mb_strlen($delimiter) > 1) {
			throw new InvalidArgumentException(sprintf('%s: delimiter cannot be an empty or reserved character and must be a single character.', __CLASS__));
		}
		$this->delimiter = $delimiter;
		return $this;
	}
	
	/**
	 * Set value enclosure.
	 *
	 * @param string $enclosure
	 * 
	 * @return CsvResponse
	 * @throws InvalidArgumentException
	 */
	public function setEnclosure(string $enclosure): CsvResponse
	{
		if (true === empty($enclosure) || preg_match('/[\n\r]/s', $enclosure) === 1 || mb_strlen($enclosure) > 1) {
			throw new InvalidArgumentException(sprintf('%s: enclosure cannot be an empty or reserved character and must be a single character.', __CLASS__));
		}
		$this->enclosure = $enclosure;
		return $this;
	}
	
	/**
	 * Set escape char.
	 * 
	 * @param string $escapeChar
	 * 
	 * @return CsvResponse
	 * @throws InvalidArgumentException
	 */
	public function setEscapeChar(string $escapeChar): CsvResponse
	{
		if (true === empty($escapeChar) || preg_match('/[\n\r]/s', $escapeChar) === 1 || mb_strlen($escapeChar) > 1) {
			throw new InvalidArgumentException(sprintf('%s: escape char cannot be an empty or reserved character and must be a single character.', __CLASS__));
		}
		$this->escapeChar = $escapeChar;
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
		if ($formatter !== null && false === is_callable($formatter)) {
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
		if ($formatter !== null && false === is_callable($formatter)) {
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
		if (false === empty($this->filename)) {
			$attachment .= sprintf('; filename="%s"', $this->filename);
		}
		$httpResponse->setHeader('Content-Disposition', $attachment);
		$data = $this->formatCsv();
		$httpResponse->setHeader('Content-Length', mb_strlen($data));
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
		if (true === empty($this->data)) {
			return '';
		}
		ob_start();
		$buffer = fopen('php://output', 'w');
		// if output charset is not UTF-8
		$recode = strcasecmp($this->outputCharset, 'utf-8');
		foreach ($this->data as $n => $row) {
			if (true === $row instanceof Traversable) {
				$row = iterator_to_array($row);
			}
			if (false === is_array($row)) {
				throw new InvalidArgumentException(sprintf('%s: row "%d" must be array or instance of Traversable, "%s" given.', __CLASS__, $n, gettype($row)));
			}
			if ($n === 0 && $this->addHeading === true) {
				$header = $this->getRowHeader($row, $recode);
				fputcsv($buffer, $header, $this->delimiter, $this->enclosure, $this->escapeChar);
			}
			$row = $this->getRowData($row, $recode);
			fputcsv($buffer, $row, $this->delimiter, $this->enclosure, $this->escapeChar);
		}
		fclose($buffer);
		$return = ob_get_clean();
		if ($return === false) {
			throw new LogicException('Output buffering is not active.');
		}
		return $return;
	}
	
	/**
	 * Get formatted and recoded header (if formatter and recode is set - if not not changed data will be returned).
	 * 
	 * @param array $row
	 * @param int $recode
	 * 
	 * @return array
	 */
	protected function getRowHeader(array $row, int $recode): array
	{
		$labels = array_keys($row);
		if ($this->headingFormatter !== null || $recode !== 0) {
			foreach ($labels as &$label) {
				if ($this->headingFormatter !== null) {
					$label = call_user_func($this->headingFormatter, $label);
				}
				if ($recode !== 0) {
					$label = iconv('utf-8', $this->outputCharset . '//TRANSLIT', $label);
				}
			}
		}
		return $labels;
	}
	
	/**
	 * Get formatted and recoded values (if formatter and recode is set - if not not changed data will be returned).
	 * 
	 * @param array $row
	 * @param int $recode
	 * 
	 * @return array
	 */
	protected function getRowData(array $row, int $recode): array
	{
		if ($this->dataFormatter === null && $recode === 0) {
			return $row;
		}
		foreach ($row as &$value) {
			if ($this->dataFormatter !== null) {
				$value = call_user_func($this->dataFormatter, $value);
			}
			if ($recode !== 0) {
				$value = iconv('utf-8', $this->outputCharset . '//TRANSLIT', $value);
			}
		}
		return $row;
	}

}
