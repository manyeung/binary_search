<?php

/**
 * search for a result from a list of files by using binary search
 */
class BinarySearch
{
	protected $sourcePattern;
	protected $searchValueFn;

	protected $expandStates;

	public function __construct($sourcePattern, $searchValueFn)
	{
		$this->sourcePattern = $sourcePattern;
		$this->searchValueFn = $searchValueFn;

		$this->resetExpandState();
	}

	protected function getValue($id)
	{
		$path = str_replace('[id]', $id, $this->sourcePattern);
		$content = file_get_contents($path);
		$content = json_decode($content, true);
		$value = call_user_func($this->searchValueFn, $content);
		return $value;
	}

	public function search($list, $search)
	{
		if (empty(count($list))) {
			return null;
		}

		$index = floor(count($list) / 2);
		$id = $list[$index];
		$value = $this->getValue($id);

		if ($value > $search) {
			return $this->search(array_slice($list, $index + 1), $search);
		}
		if ($value < $search) {
			return $this->search(array_slice($list, 0, $index), $search);
		}
		return $id;
	}

	public function resetExpandState()
	{
		$this->expandStates = array(
			'lock' => array(false, false),
			'first' => array('id' => null),
			'last' => array('id' => null),
			'loop' => 100,
		);
	}

	public function expand($list, $search, $range, $offset, $direction)
	{
		extract($this->expandStates);

		$count = count($list);

		if (in_array($direction, array('both', 'left'))) {
			$start = $range[0] - $offset;
			$start = $start >= 0 ? $start : 0;
		} else {
			$start = $range[0];
		}

		if (in_array($direction, array('both', 'right'))) {
			$end = $range[1] + $offset;
			$end = $end <= $count ? $end : $count;
		} else {
			$end = $range[1];
		}

		if ($start == 0) {
			$lock[0] = true;
		}
		if ($end == $count) {
			$lock[1] = true;
		}

		$newList = array_slice($list, $start, $end - $start);

		// prevent infinity loop
		if (empty($loop--)) {
			throw new \Exception('prevent infinity loop!');
		}

		if ($first['id'] != $newList[0]) {
			$id = $newList[0];
			$value = $this->getValue($id);
			$first = compact('id', 'value');
		}
		if ($last['id'] != $newList[count($newList) - 1]) {
			$id = $newList[count($newList) - 1];
			$value = $this->getValue($id);
			$last = compact('id', 'value');
		}

		$this->expandStates = array_merge($this->expandStates, compact('lock', 'first', 'last', 'loop'));

		if ($first['value'] > $search && $search > $last['value']) {
			$this->expandStates['lock'] = array(true, true);
			return $this->expand($list, $search, array($start + 1, $end - 1), $offset, 'none');
		}
		if ($first['value'] > $search) {
			$this->expandStates['lock'][0] = true;
			return $this->expand($list, $search, array($start + 1, $end), $offset, 'none');
		}
		if ($search > $last['value']) {
			$this->expandStates['lock'][1] = true;
			return $this->expand($list, $search, array($start, $end - 1), $offset, 'none');
		}

		if ($lock[0] && $lock[1]) {
			return $newList;
		}
		if ($lock[0]) {
			return $this->expand($list, $search, array($start, $end), $offset, 'right');
		}
		if ($lock[1]) {
			return $this->expand($list, $search, array($start, $end), $offset, 'left');
		}
		return $this->expand($list, $search, array($start, $end), $offset, 'both');
	}
}
