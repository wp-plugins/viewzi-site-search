<?php
require_once(dirname(__FILE__) . '../../../../wp-config.php');
include_once('viewzi.php');

$check_id= $_GET['vfp_id'];

if(get_option('vfp_id')) {
	$cleaned_id= str_replace( '-','', get_option('vfp_id') );
} else {
	$cleaned_id= str_replace( '-','', get_option('vss_id') );
}

if( $cleaned_id == $check_id ) {
	if( isset( $_REQUEST['term'] ) ) {
		$term= $_GET['term'];
		$filter= $_GET['type'];
		$term= urlencode($term);
	
		if( isset( $filter ) ) {
			$type= $filter;
		} else {
			$type= 0;
		}
		
		$search= new ViewziSearch();
		$search->search_all( $term,'','',$type );
	}
}

if(isset( $_REQUEST['network'] ) ) {
	if( $cleaned_id == $check_id ) {
		$return= new ViewziSearch();
		$return->status_check();
	}
}

if( isset( $_REQUEST['param_grab'] ) ) {
	if( $cleaned_id == $check_id ) {
		$poll= new ViewziSearch();
		$poll->expose();
	}
}

if( isset( $_REQUEST['cache'] ) ) {
	if( $cleaned_id == $check_id ) {
		if( $_REQUEST['startdate'] ) {
			$date= $_REQUEST['startdate'];
		} else {
			$date= '';
		}
		
		if( $_REQUEST['call'] ) {
			$call= $_REQUEST['call'];
		} else {
			$call= '';
		}
		
		$download= new ViewziSearch();
		$download->lucene_endpoint( $date, $call );
	}
}
?>