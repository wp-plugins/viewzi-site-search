<?php 
/*
Plugin Name: Viewzi for WordPress
Plugin URI: http://viewzi.com
Description: 
Author: Chris J. and Manoj
Version: 1.1
*/
if( !method_exists( 'Services_JSON','Services_JSON') ) {
	include_once('lib/json.php');
}

class ViewziSearch
{
	function get_fuid() {
		$key= '9ad2e2ad13c6d4e2f09d26116f0f29d4';
		$endpoint= 'http://flickr.com/services/rest/?';
		$email= get_option("vfp_flickr");
		$url= $endpoint . 'method=flickr.people.findByEmail&find_email=' . $email . '&api_key=' . $key;
		$nsid= $this->get_nsid( $url );
		//return $nsid;
		add_option("vfp_nsid", $nsid );
		
	}
	
	function status_check() {
		$json = new Services_JSON();
		global $wpdb;
		$status= array();
		
		$version= phpversion();
		$mysqlversion= mysql_get_server_info();
		
		$status['info']['type']= 'WORDPRESSMU';
		$status['info']['version']= '1.1';
		$status['info']['blog_count']= "$count";
		$status['info']['php_version']= "$version";
		$status['info']['mysql_version']= "$mysqlversion";
		$status = $json->encode($status);
		echo $status;
	}
	
	function install_viewzi() {
	global $wpdb, $db_version;
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	$table= $wpdb->prefix . 'viewzi_clients';

	$q= '';
		if( $wpdb->get_var( "show tables like '$table'" ) != $table ) {
			$q= "CREATE TABLE " . $table . "( 
				id int NOT NULL AUTO_INCREMENT,
				site_url varchar(255) NOT NULL,
				vfp_id varchar(20) NOT NULL,
				active int(1) NOT NULL,
				UNIQUE KEY id (id)
			);";
		}
		
		if( $q != '' ) {
			dbDelta( $q );
		}
		
		$site= get_bloginfo('siteurl');
		$vfp_id= get_option('vfp_id');
		
		if( !$wpdb->get_var( "SELECT id FROM $table WHERE vfp_id = '$vfp_id'" ) ) {
			$i= "INSERT INTO " . $table . " (id,site_url,vfp_id, active) VALUES('', '" . $site . "','" . $vfp_id . "',1)";
			$query= $wpdb->query( $i );
		}
	}
	
	function search_all( $term, $limit=100, $offset=0, $filter=0 ) {
		global $wpdb;
		if( $term != '' ) {	
			$json = new Services_JSON();
			$all_results= array();
			$found= array();
			$i=0;
			$terms= explode('+', $term);
			$needle= implode(" ", $terms);
			
			if( $filter==1 || $filter== 0 ) {
				foreach( $this->search_posts( $terms, $limit=100, $offset=0 ) as $post ) {
					$all_results[]= $post;
				}
			}
			
			if( $filter==2 || $filter== 0 ) {
				foreach( $this->search_comments( $terms, $limit=100, $offset=0 ) as $comment ) {
					$all_results[]= $comment;
				}
			}
						
			foreach( $all_results as $result ) {
				if( isset( $result->ID ) ) {
					$id= $result->ID;
				} else {
					if( isset( $result->comment_post_ID ) ) {
						$id= $result->comment_post_ID;
					}
				}

				$found[$i]->title= strip_tags( $result->post_title );
				
				if( isset( $result->post_content ) ) {
					$found[$i]->type= 'post';
					$found[$i]->content= $this->excerpt( $needle, $result->post_content );
				} else {
					if( isset( $result->comment_content) ) {
						$found[$i]->type= 'comment';
						$found[$i]->content= $this->excerpt( $needle, $result->comment_content );
					}
				}
				
				$found[$i]->link= get_permalink( $id );
				
				if( isset( $result->post_content ) ) {
					$found[$i]->date= $result->post_date;
				} else {
					$found[$i]->date= $result->comment_date;	
				}
				
				if( isset( $result->post_content ) ) {
					$table= $wpdb->users;
				} else {
					$table= $wpdb->comments;
				}
				if( $table== $wpdb->comments ) {
					$found[$i]->author= $result->comment_author;
				} else {
					$found[$i]->author= $this->get_author( $result->post_author, $id, $table );	
				}
				
				$found[$i]->comment_count= get_comments_number( $id );
				
				if( $get_the_tags != false ) {
					$found[$i]->tags= get_the_tags( $id );
				} else {
					$found[$i]->tags= get_the_category( $id );	
				}
				
				$i++;
			}
			
			usort( $found, array( $this, 'date_sort' ) );
			
			$output = $json->encode($found);
			echo $output;
			//return $output;
		} else {
			echo 'Give me something to work with, please!';
			//return false;
		}
	}

	function excerpt( $needle, $haystack ) {
		$haystack= strip_tags($haystack);
		$offset= 300;
		$location= stripos($haystack,$needle);
		$start= max($location - $offset, 0);
		$end= $start + (2 * $offset) + strlen($needle);
		$end= min(strlen($haystack), $end);
		$length= $end - $start;
		$result= '';
		if( $location > 0 ) {
			$result .='...';
		}
		$result .= substr( $haystack, $start, $length);
		if( strlen( $haystack ) != $end ) {
			$result .='...';
		}
		return $result;
	}

	function search_posts( $terms, $limit=100, $offset=0 ) {
		global $wpdb;
		$results= $wpdb->get_results( "SELECT ID, post_author, post_date, post_title, post_content FROM $wpdb->posts WHERE MATCH (post_title, post_content) AGAINST ('".implode(" ",$terms)."' IN BOOLEAN MODE) AND post_status= 'publish' ORDER BY post_date DESC LIMIT $limit OFFSET $offset" );
	return $results;
	}
	
	function search_comments( $terms, $limit=100, $offset=0 ) {
		global $wpdb;
		$results= $wpdb->get_results( "SELECT $wpdb->comments.comment_author, $wpdb->comments.comment_date, $wpdb->comments.comment_content, $wpdb->comments.comment_post_ID, $wpdb->posts.post_title FROM $wpdb->comments, $wpdb->posts WHERE MATCH($wpdb->comments.comment_content) AGAINST ('".implode(" ",$terms)."' IN BOOLEAN MODE) AND $wpdb->comments.comment_approved= 1 AND $wpdb->posts.ID = $wpdb->comments.comment_post_ID ORDER BY $wpdb->comments.comment_date DESC LIMIT $limit OFFSET $offset" );
		return $results;
	}
	
	function lucene_search_p( $date, $limit=100 ) {
		global $wpdb;
		$post= $wpdb->get_results( "SELECT ID, post_author, post_date,post_modified, post_title, post_content FROM $wpdb->posts WHERE post_modified > '" . $date . "' AND post_status= 'publish' AND post_type != 'revision' ORDER BY post_modified ASC LIMIT $limit" );
	return $post;
	}
	
	function lucene_search_c( $date, $limit=100 ) {
		global $wpdb;
		$comments= $wpdb->get_results( "SELECT $wpdb->comments.comment_ID as ID, $wpdb->comments.comment_author, $wpdb->comments.comment_date, $wpdb->comments.comment_content, $wpdb->comments.comment_post_ID, $wpdb->posts.post_title FROM $wpdb->comments, $wpdb->posts WHERE $wpdb->comments.comment_approved= 1 AND $wpdb->posts.ID= $wpdb->comments.comment_post_ID AND $wpdb->comments.comment_date > '" . $date . "' ORDER BY $wpdb->comments.comment_date ASC LIMIT $limit" );
	return $comments;
	}
	
	function get_author( $person_id, $id, $table ) {
		global $wpdb;
		if( $table == $wpdb->users ) {
			$author= $wpdb->get_results( "SELECT display_name FROM $table WHERE ID = $person_id" );
			return $author[0]->display_name;
		} else {
			$author= $wpdb->get_results( "SELECT comment_author FROM $table WHERE comment_post_ID = $id" );
			return $author[0]->comment_author;
		}
	}
	
	function remote_request( $url ) {
		$ch= curl_init();
		$useragent='cURL';
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER,1 );
		curl_setopt(	$ch, CURLOPT_USERAGENT, $useragent );
		curl_setopt(	$ch, CURLOPT_SSL_VERIFYPEER, false	);
		$result=curl_exec($ch);
		curl_close($ch);
		return $result;
	}
	
	function get_nsid( $url ) {
		$result= $this->remote_request( $url );
		$parser= xml_parser_create('UTF-8');
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1); 
		xml_parse_into_struct($parser, $result, $vals, $index); 
		xml_parser_free($parser);
		$nsid= $vals[1]['attributes']['NSID'];
		return $nsid;
	}
	
	function add_vssid( $value ) {
		$name= 'vss_id';
		add_option($name,$value);
	}
	
	function get_params() {
		global $wpdb;
		$params= $wpdb->get_results("SELECT option_id, option_name, option_value FROM $wpdb->options WHERE option_name LIKE 'vfp_%'");
		return $params;
	}
	
	function remove_param( $id ) {
		global $wpdb;
		$burninate= $wpdb->get_results("DELETE FROM $wpdb->options WHERE option_id=$id");
		$this->update_viewzi();
	}

	function get_oldest() {
		global $wpdb;
		$alpha = $wpdb->get_results("SELECT ID, post_date FROM $wpdb->posts WHERE post_date != '0000-00-00 00:00:00' AND post_status = 'publish' ORDER BY post_date LIMIT 1");
		return $alpha;
	}

	function lucene_endpoint($date, $call) {
		global $wpdb;
		$json = new Services_JSON();
		$all_results= array();
		$found= array();
		$i=0;
		if($date== '') {
		$alpha = $wpdb->get_results("SELECT ID, post_date FROM $wpdb->posts WHERE post_date != '0000-00-00 00:00:00' AND post_status = 'publish' ORDER BY post_date LIMIT 1");
		$check_date= $alpha[0]->post_date;
		} else {
			$check_date= date( 'Y-m-d H:i:s' ,strtotime($date));
		}
		if( $call == 'posts' ) {
			foreach( $this->lucene_search_p( $check_date, $limit=100 ) as $post ) {
				$all_results[]= $post;
			}
		}
		
		if( $call == 'comments') {
			foreach( $this->lucene_search_c( $check_date, $limit=100 ) as $comment ) {
				$all_results[]= $comment;
			}
		}
				
		foreach( $all_results as $result ) {
			if( isset( $result->ID ) ) {
				$id= $result->ID;
				$found[$i]->id= $id;
			} else {
				if( isset( $result->comment_post_ID ) ) {
					$id= $result->comment_post_ID;
					$found[$i]->id= $id;
				}
			}

			$found[$i]->title= strip_tags( $result->post_title );
			
			if( isset( $result->post_content ) ) {
				$found[$i]->type= 'post';
				$postId = $id;
				$found[$i]->content= strip_tags( $result->post_content );
				$found[$i]->link= get_permalink( $id );
			} else {
				if( isset( $result->comment_content) ) {
					$found[$i]->type= 'comment';
					$found[$i]->content= strip_tags( $result->comment_content );
					$postId = $result->comment_post_ID;
					$found[$i]->link= get_permalink( $postId );
				}
			}
			
			if( isset( $result->post_content ) ) {
				$found[$i]->date= $result->post_modified;
			} else {
				$found[$i]->date= $result->comment_date;	
			}
			
			if( isset( $result->post_content ) ) {
				$table= $wpdb->users;
			} else {
				$table= $wpdb->comments;
			}
			
			$found[$i]->author= $this->get_author( $result->post_author, $postId, $table );
			$found[$i]->comment_count= get_comments_number( $postId );
			
			if( $get_the_tags != false ) {
				$found[$i]->tags= get_the_tags( $postId );
			} else {
				$found[$i]->tags= get_the_category( $postId );	
			}
			
			$i++;
		}
		
		usort( $found, array( $this, 'lucene_sort' ) );
		$output = $json->encode($found);
		echo $output;
	}

	function lucene_sort($a, $b) {
	    $a = $a->date;
	    $b = $b->date;
		if ( $a == $b ) {
			return 0;
		} else {
			return ( $a < $b ) ? -1 : 1;
		}
	}
		
	function date_sort($a, $b) {
	    $a = $a->date;
	    $b = $b->date;
		if ( $a == $b ) {
			return 0;
		} else {
			return ( $a > $b ) ? -1 : 1;
		}
	}
	
	function expose() {
		$json = new Services_JSON();
		$options= $this->get_params();
		echo $json->encode($options);
	}
	
	function update_viewzi() {
		$json = new Services_JSON();
		$options= $this->get_params();
		$data= $json->encode($options);
		$url= 'http://viewzi.com/vfp.php?update' . $data;
		return $this->remote_request( $url );
	}
	
	function addMenu() {
		add_management_page( __('Viewzi Site Search'), __('Viewzi Site Search'), 9, basename(__FILE__), array( 'ViewziSearch', 'viewziMenu' ) );
	}
	
	function viewziMenu() {
		if( get_option('vss_id') == '' ) {
			$vfp= new ViewziSearch();
			if ( $_GET['page'] == basename(__FILE__) ) {
		        if( 'add_param' == $_REQUEST['action'] ) {
					$value= $_REQUEST['vss_id'];
					$vfp->add_vssid( $value );
				}
			}
	?>
	<div class="wrap">
		<h2>Configure Viewzi Site Search</h2>
		<?php global $wp_version; if( $wp_version > '2.6.5' ) { ?>
		<form method="post" action="tools.php?page=viewzi.php&amp;add_new=true" class="form-table" style="margin-bottom:30px;">
		<?php } else { ?>
		<form method="post" action="edit.php?page=viewzi.php&amp;add_new=true" class="form-table" style="margin-bottom:30px;">
		<?php } ?>
			<p><label for="vss_id">Your Viewzi Site Search ID:</label> <input type="text" id="vss_id" name="vss_id" /></p>
			<p><small>Don't have a Viewzi Site Search ID yet? <a href="http://www.viewzi.com/profile/vss.php?force_login=1&amp;action=create" title="Click here to get your ID">Click here to get one</a>.</small></p>
			<input type="hidden" name="action" value="add_param" />
			<p class="submit"><input name="save" type="submit" value="Save" /></p>
		</form>
	</div>
	<?php
		} else {
	?>
	<div class="viewzi_viewport" style="position:relative;">
		<iframe src="http://www.viewzi.com/profile/vss.php?id=plugin&type=wordpress&siteUrl=<?php echo bloginfo('siteurl'); ?>&siteName=<?php echo bloginfo('name'); ?>&p_version=1.1" id="viewzi_admin" border="0" scroll="auto"></iframe>
	</div>
<?php
	}
	}

	function viewzi_admin_head() {
	?>
	<script src="http://s.viewzi.com/script/vss_admin_plugin.js"></script>
	<link rel="stylesheet" href="http://s.viewzi.com/css/vss_admin_plugin.css" media="all" type="text/css" />
	<?php
	}
	
	function viewzi_site_search() {
		if( get_option( 'vss_id' ) ) {
			$uuid= get_option('vss_id');
		} else {
			$uuid= get_option('vfp_id');
			$uuid= implode('-', str_split($uuid, 4));
		}
		echo '<script type="text/javascript" src="http://vfp.viewzi.com/includes/<?php echo $uuid; ?>.js"></script>';
	}
}

ViewziSearch::install_viewzi();
add_action ('admin_menu', array( 'ViewziSearch', 'addMenu' ) );
add_action ('admin_head', array( 'ViewziSearch', 'viewzi_admin_head' ) );
?>