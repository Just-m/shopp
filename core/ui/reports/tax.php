<?php

class TaxReport extends ShoppReportFramework implements ShoppReport {

	function query () {
		global $bbdb;
		extract($this->options, EXTR_SKIP);
		$data =& $this->data;

		$where = array();

		$where[] = "$starts < UNIX_TIMESTAMP(o.created)";
		$where[] = "$ends > UNIX_TIMESTAMP(o.created)";

		$where = join(" AND ",$where);
		$id = $this->timecolumn('o.created');
		$orders_table = DatabaseObject::tablename('purchase');
		$purchased_table = DatabaseObject::tablename('purchased');
		$query = "SELECT CONCAT($id) AS id,
							UNIX_TIMESTAMP(o.created) as period,
							COUNT(DISTINCT o.id) AS orders,
							SUM(o.subtotal) as subtotal,
							SUM(IF(p.unittax > 0,p.total,0)) AS taxable,
							AVG(p.unittax/p.unitprice) AS rate,
							SUM(o.tax) as tax
					FROM $purchased_table AS p
					LEFT JOIN $orders_table AS o ON p.purchase=o.id
					WHERE $where
					GROUP BY CONCAT($id)";

		$data = DB::query($query,'array','index','id');

	}

	function columns () {
		return array(
			'period'=>__('Period','Shopp'),
			'orders'=>__('Orders','Shopp'),
			'subtotal'=>__('Subtotal','Shopp'),
			'taxable'=>__('Taxable Amount','Shopp'),
			'rate'=>__('Tax Rate','Shopp'),
			'tax'=>__('Total Tax','Shopp')
		);
	}

	function setup () {
		extract($this->options, EXTR_SKIP);

		$this->screen = $screen;

		$this->Chart = new ShoppReportChart();
		$Chart = $this->Chart;
		$Chart->series(0,__('Taxable','Shopp'));
		$Chart->series(1,__('Total Tax','Shopp'));
		$Chart->settings(array(
			'yaxis' => array('tickFormatter' => 'asMoney')
		));

		// Post processing for stats to fill in date ranges
		$data =& $this->data;
		$stats =& $this->report;

		$month = date('n',$starts); $day = date('j',$starts); $year = date('Y',$starts);

		$range = $this->range($starts,$ends,$scale);
		$Chart->timeaxis('xaxis',$range,$scale);

		$this->total = $range;
		$stats = array_fill(0,$range,true);

		foreach ($stats as $i => &$s) {
			$s = new StdClass();
			$s->ts = $s->orders = $s->items = 0;

			$index = $i;
			switch (strtolower($scale)) {
				case 'hour': $ts = mktime($i,0,0,$month,$day,$year); break;
				case 'week':
					$ts = mktime(0,0,0,$month,$day+($i*7),$year);
					$index = sprintf('%s %s',(int)date('W',$ts),date('Y',$ts));
					break;
				case 'month':
					$ts = mktime(0,0,0,$month+$i,1,$year);
					$index = sprintf('%s %s',date('n',$ts),date('Y',$ts));
					break;
				case 'year':
					$ts = mktime(0,0,0,1,1,$year+$i);
					$index = sprintf('%s',date('Y',$ts));
					break;
				default:
					$ts = mktime(0,0,0,$month,$day+$i,$year);
					$index = sprintf('%s %s %s',date('j',$ts),date('n',$ts),date('Y',$ts));
					break;
			}

			if (isset($data[$index])) {
				$props = get_object_vars($data[$index]);
				foreach ( $props as $property => $value)
					$s->$property = $value;
			}
			$s->period = $ts;

			$Chart->data(0,$s->period,(int)$s->taxable);
			$Chart->data(1,$s->period,(int)$s->tax);
		}

	}

	static function orders ($data) { return intval($data->orders); }

	static function subtotal ($data) { return money($data->subtotal); }

	static function taxable ($data) { return money($data->taxable); }

	static function tax ($data) { return money($data->tax); }

	static function rate ($data) { return percentage($data->rate*100); }



}