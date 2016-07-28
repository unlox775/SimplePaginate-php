<?php

/********************
 *
 * SimplePaginate - Simple-as-possibly pagination of passed-in SQL query
 *
 * 2015 by David Buchanan - http://joesvolcano.net/
 *
 * GitHub: https://github.com/unlox775/SimplePaginate-php
 *
 ********************/

function paginate($dbh, $sql, $spec = array(), $page_no = 1, $order_by = '', $pager_total = null) {
    if ( $dbh instanceof Zend_Db_Adapter_Pdo_Abstract ) { $dbh = $dbh->getConnection(); }

    ///  Default Specs
    $default_spec = array( 'per_page' => 30,
                           'show_nearby_pages' => 0,
                           'first_and_last_in_page_list' => true,
                           'auto_fix_out_of_range' => true,
                           'pdo_fetch_mode' => PDO::FETCH_BOTH,
                           'bad_params' => array('_pjax'),
                           'bad_params' => array('_pjax'),
                           );
    $spec = array_merge( $default_spec, $spec );

    $pager = array();

    ///  Run the query with COUNT(*)
    if ( ! is_null($pager_total) && is_numeric($pager_total) ) {
        $pager['total'] = $pager_total;
    }
    else {
        $total_sql = "SELECT COUNT(*) FROM ( $sql ) s";
        $sth = $dbh->query($total_sql);
        if ( $sth === false ) { return trigger_error("paginate() error: Bad SQL Query: [$sql]", E_USER_ERROR); }
        list( $pager['total'] ) = $sth->fetch(PDO::FETCH_NUM);
        if ( ! is_numeric( $pager['total'] ) ) { return trigger_error("paginate() error: Bad SQL Query, non-numeric total ( ". $pager['total'] ." ): [$total_sql]", E_USER_ERROR); }
    }

	///  Page num calc
	$pager['last_page'] = $pager['total'] == 0 ? 1 : ( (int) (($pager['total'] - 1) / $spec['per_page']) ) + 1;
	if ( $spec['auto_fix_out_of_range'] ) {
	    if ( ! is_numeric( $page_no ) || $page_no < 1 ) { $page_no = 1; }
	    else if ( $page_no > $pager['last_page'] )      { $page_no = $pager['last_page']; }
	}
	$limit_start = ($page_no - 1) * $spec['per_page'];
	
	///  Run the actual query...
	$data_sql = "SELECT * FROM ( $sql ) s ". $order_by ." LIMIT $limit_start, ". (int) $spec['per_page'];
	$sth = $dbh->query($data_sql);
	if ( $sth === false ) { return trigger_error("paginate() error: Bad SQL Query: [$sql]"); }
	$data = $sth->fetchAll($spec['pdo_fetch_mode']);
	if ( ! is_array( $data ) ) { return trigger_error("paginate() error: Bad SQL Query (error getting data): [$data_sql]", E_USER_ERROR); }

	return PaginateHelper::__prepareResultsArray($data, $pager, $spec, $page_no);
}
class PaginateHelper{
    private $spec = array();
    public function __construct($spec){
        $this->spec = $spec;
    }
    public function url($page, $get_param_key = 'p'){
        $get = $_GET;
        //remove bad params
        foreach($this->spec['bad_params'] as $bad){ if(isset($get[$bad])){ unset($get[$bad]); } }
        $get[$get_param_key] = $page;
        return '?'.http_build_query($get);
    }

	public static function __prepareResultsArray($data, $pager, $spec, $page_no = 1){

		$pager['page_no'] = $pager['this_page'] = $page_no;

	    $page_list = array((int) $page_no => true);
	    if ( $spec['first_and_last_in_page_list'] ) {
	        $page_list[1] = true;
	        $page_list[ $pager['last_page'] ] = true;
	    }
	    if ( ! empty( $spec['show_nearby_pages'] ) ) {
	        foreach ( range( 1, $spec['show_nearby_pages'] ) as $prox ) {
	            foreach( array(1,-1) as $sign ) { //  positive and negative
	                $add_page_num = (int) $page_no + ($prox * $sign);
	                if ( $add_page_num > $pager['last_page'] || $add_page_num < 1 ) { continue; }
	                $page_list[ $add_page_num ] = true;
	            }
	        }
	    }
	    ksort($page_list,SORT_NUMERIC);
	    $pager['page_list'] = array_keys($page_list);
	    ///  Next / Prev
	    $pager['prev_page'] = ($page_no - 1) < 1                   ? null : ($page_no - 1);
	    $pager['next_page'] = ($page_no + 1) > $pager['last_page'] ? null : ($page_no + 1);
	    $pager['helper'] = new PaginateHelper($spec);
	
	    return( array( 'results' => $data, 'pagination' => $pager ) );
	}
}
