<?php

class TypeAheadField extends TextField {

	private static $allowed_actions = [
		'suggestion',
		'prefetch',
	];

	/** @var string Class we are searching (when using @SS_List) */
	public $sourceClass;

	/** @var string|array Search filter when using a @SS_List */
	public $sourceField = null;

	/** @var string Name of the field to use as a suggestion */
	public $refField = 'Title';

	/** @var string Name of the field to use as a value */
	public $valField = 'Title';

	/**
	 * List used to search in database (if not provided, uses source class and source field instead)
	 * Will also accept a URL to override field suggestion
	 * @var SS_List|array|string|Closure
	 */
	public $sourceList;

	/** @var string|boolean The url to use as a live search */
	public $suggestURL;

	/**
	 * Maximum number of search results to display per search
	 *
	 * @var integer
	 */
	public $limit = 10;

	/**
	 * Minimum number of characters that a search will act on
	 *
	 * @var integer
	 */
	public $minSearchLength = 2;

	/**
	 * Flag indicating whether a selection must be made from the existing list (creating a tag field)
	 * By default free text entry is not allowed.
	 *
	 * @var boolean
	 */
	public $requireSelection = false;

	/**
	 * Prefetch a number of results so user has some to select from
	 * Will also accept a URL to override field prefetch
	 *
	 * @var integer|String
	 */
	public $prefetch = 10;

	/** @var array Options that will be disabled but still display */
	public $disabledOptions = [];

	/** @var array Options that will always be selected */
	public $lockedOptions = [];

	function __construct(
		$name,
		$title = null,
		$value = '',
		$sourceList = null,
		$sourceField = null,
		$refField = '',
		$valField = ''
	) {
		// set source
		$this->sourceList  = $sourceList;
		$this->sourceField = $sourceField;

		if($refField)
			$this->refField = $refField;

		if($valField)
			$this->valField = $valField;

		// construct the TextField
		parent::__construct($name, $title, $value);
	}

	/**
	 * Set source class
	 *
	 * @param $value
	 *
	 * @return $this
	 */
	function setSourceClass($value) {
		$this->sourceClass = $value;

		return $this;
	}

	function setSourceField($value) {
		$this->sourceField = $value;

		return $this;
	}

	function setSourceList($value) {
		$this->sourceList = $value;

		return $this;
	}

	function getSourceList() {
		if ($this->sourceList && is_string($this->sourceList)) {
			return null;
		}

		if (!$this->sourceList) {
			if ($class = $this->SourceClass) {
				$this->sourceList = DataList::create($class);
			}
		}

		return $this->sourceList;
	}

	public function getSourceClass() {
		if ($class = $this->sourceClass) {
			return $class;
		}

		$form = $this->getForm();
		if (!$form) {
			return null;
		}

		$record = $form->getRecord();
		if (!$record) {
			return null;
		}

		return $record->ClassName;
	}

	function getSuggestURL() {
		if ($this->sourceList && is_string($this->sourceList)) {
			return $this->sourceList;
		}

		return $this->suggestURL !== null ? $this->suggestURL : $this->form ? $this->Link('suggestion') : '';
	}

	function setSuggestURL($val = null) {
		$this->suggestURL = $val;

		return $this;
	}

	function getPrefetchURL() {
		if ($this->prefetch && is_string($this->prefetch)) {
			return $this->prefetch;
		}

		return $this->prefetch && $this->form ? $this->Link('prefetch') : null;
	}

	function setPrefetch($val = null) {
		$this->prefetch = $val;

		return $this;
	}

	function setMinimumSearchLength($val = 2) {
		$this->minSearchLength = $val;

		return $this;
	}

	function requireSelection($flag = true) {
		$this->requireSelection = $flag;

		return $this;
	}

	function getAttributes() {
		$this->extraClasses[] = 'text';

		$attributes = array_merge(
			[
				'data-suggest-url'       => $this->SuggestURL ? $this->SuggestURL . '?q=%QUERY' : false,
				'data-prefetch-url'      => $this->PrefetchURL,
				'data-min-length'        => $this->minSearchLength,
				'data-require-selection' => $this->requireSelection,
				'data-name'              => strtolower($this->ID()),
				'data-templates.empty'  => _t('TypeAheadField.NO_MATCHES', 'No matches found'),
			], parent::getAttributes(), [
				'autocomplete' => 'off'
			]
		);

		if(!$this->form) {
			if ($list = $this->SourceList) {
				$results = $this->results('', $list);
			} else {
				$results = [];
			}

			$attributes['data-local'] = json_encode($results);
		}

		return $attributes;
	}

	function Field($properties = []) {
		if (!$this->config()->exclude_js) {
			$this->includeJs();
		}

		if (!$this->config()->exclude_css) {
			$this->includeCss();
		}

		return parent::Field($properties);
	}

	function suggestion(HTTPRequest $r) {
		$results = [];

		$list = $this->SourceList;

		if (!$list) {
			$response = new SS_HTTPResponse(json_encode($results), 200, 'fail');
			$response->addHeader('Content-type', 'application/json');

			return $response;
		}

		if ($this->limit === false) {
			$limit = null;
		} else {
			$limit = $this->limit ? $this->limit : 10;
		}

		// input
		$results = $this->results(Convert::raw2sql($r->getVar('q')), $list, null, $limit);

		$response = new SS_HTTPResponse(json_encode($results), 200, '');
		$response->addHeader('Content-type', 'application/json');

		return $response;
	}

	function prefetch(HTTPRequest $r) {
		if ($this->prefetch === true) {
			$limit = null;
		} else {
			$limit = $this->prefetch ? $this->prefetch : 10;
		}

		if ($list = $this->SourceList) {
			$results = $this->results('', $list, null, $limit);
		} else {
			$results = [];
		}

		$response = new SS_HTTPResponse(json_encode($results), 200, '');
		$response->addHeader('Content-type', 'application/json');

		return $response;
	}

	public function results($q = '', $list = null, $class = null, $limit = 10) {
		$list = $list ? $list : $this->SourceList;

		if ($list instanceof Closure) {
			$list = $list($q);
		}

		$class = $class ? $class : ($list && !is_array($list)) ? $list->dataClass() : $this->SourceClass;

		if (is_array($list)) {
			$results = $this->filterArray($q, $list, $class, $limit);
		} else {
			$results = $this->filterList($q, $list, $class, $limit);
		}

		return $results;
	}

	public function filterArray($q, $list, $class = null, $limit = null) {
		$results     = [];
		$noOfResults = 0;

		if ($class && $search = $this->scaffoldSearchFields($class)) {
			$context = explode(':', reset($search));
			$pattern = '';

			if ($q && isset($context[1])) {
				switch ($context[1]) {
					case 'StartsWith':
						$pattern = '/^' . $q . '/';
						break;
					case 'EndsWith':
						$pattern = '/' . $q . '$/';
						break;
					default:
						$pattern = '/' . $q . '/';
						break;
				}
			}
		} else {
			$pattern = $q ? '/^' . $q . '/' : '';
		}

		foreach ($list as $key => $item) {
			if ($limit && $noOfResults >= $limit) {
				break;
			}

			if (ArrayLib::is_associative($list) && is_array($item)) {
				$result = $this->filterArray($q, $item, $class, $limit);

				if ($noOfResult = count($result)) {
					if ($limit && ($noOfResults + $noOfResult) > $limit) {
						array_splice($result, 0, ($noOfResult - $noOfResults));
					}

					$noOfResults += $noOfResult;
					$results[] = $this->resultGroupToMap($key, $result);
				}
			} elseif(is_array($item)) {
                $value = isset($item[$this->refField]) ? $item[$this->refField] : '';
                $key = isset($item[$this->valField]) ? $item[$this->valField] : $noOfResults;

                if (!is_string($value)) {
                    continue;
                }

                if ($pattern && preg_match($pattern, $value)) {
                    $results[] = $this->resultToMap($key, $value);
                    $noOfResults ++;
                } else {
                    $results[] = $this->resultToMap($key, $value);
                    $noOfResults ++;
                }
            } else {
                $value = is_string($item) ? $item : $key;

                if (!is_string($value)) {
                    continue;
                }

                if ($pattern && preg_match($pattern, $value)) {
                    $results[] = $this->resultToMap($key, $value);
                    $noOfResults ++;
                } else {
                    $results[] = $this->resultToMap($key, $value);
                    $noOfResults ++;
                }
            }
		}

		return $results;
	}

	public function filterList($q, $list, $class, $limit = 10) {
		$results = [];

		$search = $this->scaffoldSearchFields($class);
		$params = [];

		if ($q) {
			foreach ($search as $field) {
				$name          = (strpos($field, ':') !== false) ? $field : "$field:StartsWith";
				$params[$name] = $q;
			}

			$resulting = $list
				->filterAny($params)
				->sort(strtok($search[0], ':'), 'ASC')
				->limit($limit);
		} else {
			$resulting = $list
				->sort(strtok($search[0], ':'), 'ASC')
				->limit($limit);
		}

		if ($resulting->exists()) {
			$results = $this->resultsToMap($resulting);
		}

		return $results;
	}

	public function resultsToMap($list, $valField = 'ID', $refField = 'Title') {
		$valField = $this->valField ? $this->valField : $valField;
		$refField = $this->refField ? $this->refField : $refField;

		$results = [];

		foreach ($list as $result) {
			if($result->hasMethod('canView') && !$result->canView())
				continue;

			$results[] = $this->resultToMap($result->$valField, $result->$refField);
		}

		return $results;
	}

	public function resultToMap($id, $text, $keyField = 'id', $valField = 'text') {
		return [
			$keyField  => $id,
			$valField  => (string) $text,
			'disabled' => in_array($id, $this->disabledOptions),
			'locked'   => in_array($id, $this->lockedOptions),
		];
	}

	public function resultGroupToMap($title, $children, $valField = 'text') {
		return [
			$valField  => $title,
			'children' => $children,
		];
	}

	public function validate($validator) {
		if ($this->requireSelection) {
			$results = $this->results($this->value);

			if (!$results || !count($results)) {
				$validator->validationError(
					$this->name,
					_t('TypeAheadField.INVALID', 'Invalid value'),
					'validation',
					false
				);

				return false;
			}
		}

		return parent::validate($validator);
	}

	protected function scaffoldSearchFields($dataClass) {
		if ($this->sourceField) {
			return $this->sourceField;
		}

		$obj    = singleton($dataClass);
		$fields = null;

		if ($fieldSpecs = $obj->searchableFields()) {
			$customSearchableFields = $obj->config()->searchable_fields;
			foreach ($fieldSpecs as $name => $spec) {
				if (is_array($spec) && array_key_exists('filter', $spec)) {
					if (!$customSearchableFields || array_search($name, $customSearchableFields)) {
						$filter = 'StartsWith';
					} else {
						$filter = preg_replace('/Filter$/', '', $spec['filter']);
					}

					$fields[] = "{$name}:{$filter}";
				} else {
					$fields[] = $name;
				}
			}
		}

		if (is_null($fields)) {
			if ($obj->hasDatabaseField('Title')) {
				$fields = ['Title'];
			} elseif ($obj->hasDatabaseField('Name')) {
				$fields = ['Name'];
			}
		}

		return $fields;
	}

	protected function includeJs() {
		Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
		Requirements::javascript(THIRDPARTY_DIR . '/jquery-entwine/dist/jquery.entwine-dist.js');
		Requirements::javascript(SS_MWM_AUTOCOMPLETE_DIR . '/thirdparty/typeahead.js/dist/typeahead.bundle.js');
		Requirements::javascript(SS_MWM_AUTOCOMPLETE_DIR . '/javascript/typeahead.init.js');
	}

	protected function includeCss() {
		Requirements::css(SS_MWM_AUTOCOMPLETE_DIR . '/css/typeahead.field.css');
	}
} 