<?php

/**
 * @Project NUKEVIET 3.x
 * @Author VINADES.,JSC (contact@vinades.vn)
 * @Copyright (C) 2012 VINADES.,JSC. All rights reserved
 * @Createdate 1-27-2010 5:25
 */

if( ! defined( 'NV_MAINFILE' ) ) die( 'Stop!!!' );

//Open Graph protocol
$meta_ogp = array(
	'title' => '',
	'type' => '',
	'description' => '',
	'site_name' => '',
	'image' => '',
	'url' => ''
);

//tài khoản Google+
$id_profile_googleplus = 0;

/**
 * nv_create_submenu()
 *
 * @return void
 */
function nv_create_submenu()
{
	global $nv_vertical_menu, $module_name, $module_info, $op;

	foreach( $module_info['funcs'] as $key => $values )
	{
		if( ! empty( $values['in_submenu'] ) )
		{
			$func_custom_name = trim( ! empty( $values['func_custom_name'] ) ? $values['func_custom_name'] : $key );
			$link = NV_BASE_SITEURL . "index.php?" . NV_LANG_VARIABLE . "=" . NV_LANG_DATA . "&amp;" . NV_NAME_VARIABLE . "=" . $module_name . ( $key != "main" ? "&amp;" . NV_OP_VARIABLE . "=" . $key : "" );
			$act = $key == $op ? 1 : 0;
			$nv_vertical_menu[] = array( $func_custom_name, $link, $act );
		}
	}
}

/**
 * nv_setBlockAllowed()
 *
 * @param mixed $groups_view
 * @return
 */
function nv_setBlockAllowed( $groups_view )
{
	global $user_info;

	if( defined( 'NV_IS_SPADMIN' ) ) return true;

	$groups_view = ( string )$groups_view;
	if( $groups_view == "0" or ( $groups_view == "1" and defined( 'NV_IS_USER' ) ) or ( $groups_view == "2" and defined( 'NV_IS_MODADMIN' ) ) ) return true;
	if( defined( 'NV_IS_USER' ) and nv_is_in_groups( $user_info['in_groups'], $groups_view ) ) return true;

	return false;
}

/**
 * nv_blocks_get_content()
 *
 * @return
 */
function nv_blocks_content( $sitecontent )
{
	global $db, $module_info, $module_name, $op, $global_config, $lang_global, $site_mods, $user_info, $themeConfig;

	$_posAllowed = array();

	foreach( $themeConfig['positions']['position'] as $_pos )
	{
		$_pos = trim( ( string )$_pos['tag'] );
		unset( $matches );
		if( preg_match( "/^\[([^\]]+)\]$/is", $_pos, $matches ) ) $_posAllowed[] = $matches[1];
	}

	if( empty( $_posAllowed ) ) return $sitecontent;

	//Tim trong noi dung trang cac doan ma phu hop voi cac nhom block tren
	$_posAllowed = implode( "|", array_map( "nv_preg_quote", $_posAllowed ) );
	preg_match_all( "/\[(" . $_posAllowed . ")(\d+)?\]()/", $sitecontent, $_posReal );

	if( empty( $_posReal[0] ) ) return $sitecontent;

	$_posReal = array_combine( $_posReal[0], $_posReal[3] );

	$cache_file = NV_LANG_DATA . "_themes_" . $global_config['module_theme'] . "_" . $module_name . "_" . NV_CACHE_PREFIX . ".cache";
	$blocks = array();

	if( ( $cache = nv_get_cache( $cache_file ) ) !== false )
	{
		$cache = unserialize( $cache );
		if( isset( $cache[$module_info['funcs'][$op]['func_id']] ) ) $blocks = $cache[$module_info['funcs'][$op]['func_id']];
		unset( $cache );
	}
	else
	{
		$cache = array();

		$in = array();
		$sql = "SELECT * FROM `" . NV_MODULES_TABLE . "` AS m LEFT JOIN `" . NV_MODFUNCS_TABLE . "` AS f ON m.title=f.in_module WHERE m.act = 1 ORDER BY m.weight, f.subweight";
		$list = nv_db_cache( $sql, '', 'modules' );
		foreach( $list as $row )
		{
			$row['title'] = $db->unfixdb( $row['title'] );

			if( $row['title'] == $module_name and $row['show_func'] )
			{
				$in[] = $row['func_id'];
			}
		}

		$_result = $db->sql_query( "SELECT t1.*, t2.func_id FROM `" . NV_BLOCKS_TABLE . "_groups` AS t1
			 INNER JOIN `" . NV_BLOCKS_TABLE . "_weight` AS t2
			 ON t1.bid = t2.bid
			 WHERE t2.func_id IN (" . implode( ",", $in ) . ")
			 AND t1.theme ='" . $global_config['module_theme'] . "'
			 AND t1.active=1
			 ORDER BY t2.weight ASC" );

		while( $_row = $db->sql_fetch_assoc( $_result ) )
		{
			$_row['module'] = $db->unfixdb( $_row['module'] );
			$_row['file_name'] = $db->unfixdb( $_row['file_name'] );
			$_row['template'] = $db->unfixdb( $_row['template'] );
			$_row['position'] = $db->unfixdb( $_row['position'] );

			// Cau hinh block
			$block_config = ( ! empty( $_row['config'] ) ) ? unserialize( $_row['config'] ) : array();
			$block_config['bid'] = $_row['bid'];
			$block_config['module'] = $_row['module'];
			$block_config['title'] = $_row['title'];
			$block_config['block_name'] = substr( $_row['file_name'], 0, -4 );

			// Tieu de block
			$blockTitle = ( ! empty( $_row['title'] ) and ! empty( $_row['link'] ) ) ? "<a href=\"" . $_row['link'] . "\">" . $_row['title'] . "</a>" : $_row['title'];

			if( ! isset( $cache[$_row['func_id']] ) ) $cache[$_row['func_id']] = array();
			$cache[$_row['func_id']][] = array(
				'bid' => $_row['bid'],
				'position' => $_row['position'],
				'module' => $_row['module'],
				'blockTitle' => $blockTitle,
				'file_name' => $_row['file_name'],
				'template' => $_row['template'],
				'exp_time' => $_row['exp_time'],
				'groups_view' => $_row['groups_view'],
				'all_func' => $_row['all_func'],
				'block_config' => $block_config
			);
		}

		if( isset( $cache[$module_info['funcs'][$op]['func_id']] ) ) $blocks = $cache[$module_info['funcs'][$op]['func_id']];

		$db->sql_freeresult( $_result );
		$cache = serialize( $cache );
		nv_set_cache( $cache_file, $cache );

		unset( $cache, $in, $block_config, $blockTitle );
	}

	if( ! empty( $blocks ) )
	{
		$unact = array();
		$array_position = array_keys( $_posReal );
		foreach( $blocks as $_row )
		{
			if( $_row['exp_time'] != 0 and $_row['exp_time'] <= NV_CURRENTTIME )
			{
				$unact[] = $_row['bid'];
				continue;
			}

			//Kiem tra quyen xem block
			if( in_array( $_row['position'], $array_position ) and nv_setBlockAllowed( $_row['groups_view'] ) )
			{
				$block_config = $_row['block_config'];
				$blockTitle = $_row['blockTitle'];
				$content = '';

				if( $_row['module'] == "global" and file_exists( NV_ROOTDIR . "/includes/blocks/" . $_row['file_name'] ) )
				{
					include ( NV_ROOTDIR . "/includes/blocks/" . $_row['file_name'] );
				}
				elseif( isset( $site_mods[$_row['module']]['module_file'] ) and ! empty( $site_mods[$_row['module']]['module_file'] ) and file_exists( NV_ROOTDIR . "/modules/" . $site_mods[$_row['module']]['module_file'] . "/blocks/" . $_row['file_name'] ) )
				{
					include ( NV_ROOTDIR . "/modules/" . $site_mods[$_row['module']]['module_file'] . "/blocks/" . $_row['file_name'] );
				}
				unset( $block_config );

				if( ! empty( $content ) or defined( 'NV_IS_DRAG_BLOCK' ) )
				{
					$xtpl = null;
					$_row['template'] = empty( $_row['template'] ) ? "default" : $_row['template'];
					$_template = 'default';

					if( ! empty( $module_info['theme'] ) and file_exists( NV_ROOTDIR . "/themes/" . $module_info['theme'] . "/layout/block." . $_row['template'] . ".tpl" ) )
					{
						$xtpl = new XTemplate( "block." . $_row['template'] . ".tpl", NV_ROOTDIR . "/themes/" . $module_info['theme'] . "/layout" );
						$_template = $module_info['theme'];
					}
					elseif( ! empty( $global_config['module_theme'] ) and file_exists( NV_ROOTDIR . "/themes/" . $global_config['module_theme'] . "/layout/block." . $_row['template'] . ".tpl" ) )
					{
						$xtpl = new XTemplate( "block." . $_row['template'] . ".tpl", NV_ROOTDIR . "/themes/" . $global_config['module_theme'] . "/layout" );
						$_template = $global_config['module_theme'];
					}
					elseif( ! empty( $global_config['site_theme'] ) and file_exists( NV_ROOTDIR . "/themes/" . $global_config['site_theme'] . "/layout/block." . $_row['template'] . ".tpl" ) )
					{
						$xtpl = new XTemplate( "block." . $_row['template'] . ".tpl", NV_ROOTDIR . "/themes/" . $global_config['site_theme'] . "/layout" );
						$_template = $global_config['site_theme'];
					}
					elseif( file_exists( NV_ROOTDIR . "/themes/default/layout/block." . $_row['template'] . ".tpl" ) )
					{
						$xtpl = new XTemplate( "block." . $_row['template'] . ".tpl", NV_ROOTDIR . "/themes/default/layout" );
					}
					if( ! empty( $xtpl ) )
					{
						$xtpl->assign( 'BLOCK_TITLE', $_row['blockTitle'] );
						$xtpl->assign( 'BLOCK_CONTENT', $content );
						$xtpl->assign( 'TEMPLATE', $_template );
						$xtpl->assign( 'NV_BASE_SITEURL', NV_BASE_SITEURL );

						$xtpl->parse( 'mainblock' );
						$content = $xtpl->text( 'mainblock' );
					}
					else
					{
						$content = $_row['blockTitle'] . "<br />" . $content . "<br />";
					}

					if( defined( 'NV_IS_DRAG_BLOCK' ) )
					{
						$content = '<div class="portlet" id="bl_' . ( $_row['bid'] ) . '">
							 <p>
							 <a href="javascript:void(0)" class="block_content" name="' . $_row['bid'] . '">
							 <img style="border:none" src="' . NV_BASE_SITEURL . 'images/edit.png" alt="' . $lang_global['edit_block'] . '"/> ' . $lang_global['edit_block'] . '</a> | <a href="javascript:void(0)" class="delblock" name="' . $_row['bid'] . '">
							 <img style="border:none" src="' . NV_BASE_SITEURL . 'images/delete.png" alt="' . $lang_global['delete_block'] . '"/> ' . $lang_global['delete_block'] . '</a> | <a href="javascript:void(0)" class="outgroupblock" name="' . $_row['bid'] . '">
							 <img style="border:none" src="' . NV_BASE_SITEURL . 'images/outgroup.png" alt="' . $lang_global['outgroup_block'] . '"/> ' . $lang_global['outgroup_block'] . '</a>
							 </p>
							 ' . $content . '</div>';
					}

					$_posReal[$_row['position']] .= $content;
				}
			}
		}
		if( ! empty( $unact ) )
		{
			$sql = "UPDATE `" . NV_BLOCKS_TABLE . "_groups` SET `active`='0' WHERE `bid`IN (" . implode( ",", $unact ) . ") LIMIT " . sizeof( $unact );
			$db->sql_query( $sql );
			unlink( $cache_file );
		}
	}

	if( defined( 'NV_IS_DRAG_BLOCK' ) )
	{
		$array_keys = array_keys( $_posReal );
		foreach( $array_keys as $__pos )
		{
			$_posReal[$__pos] = '<div class="column" id="' . ( preg_replace( '#\[|\]#', '', $__pos ) ) . '">' . $_posReal[$__pos];
			$_posReal[$__pos] .= '	<span><a class="block_content" id="' . $__pos . '" href="javascript:void(0)"><img style="border:none" src="' . NV_BASE_SITEURL . 'images/add.png" alt="' . $lang_global['add_block'] . '"/> ' . $lang_global['add_block'] . '</a></span>';
			$_posReal[$__pos] .= '</div>';
		}
	}

	$sitecontent = str_replace( array_keys( $_posReal ), array_values( $_posReal ), $sitecontent );

	return $sitecontent;
}

/**
 * nv_html_meta_tags()
 *
 * @return
 */
function nv_html_meta_tags()
{
	global $global_config, $db_config, $lang_global, $key_words, $description, $module_info, $home, $client_info, $op, $page_title, $canonicalUrl, $meta_ogp, $id_profile_googleplus;

	$return = '';
	$site_description = $home ? $global_config['site_description'] : ( ! empty( $description ) ? strip_tags( $description ) : ( ! empty( $module_info['description'] ) ? $module_info['description'] : "" ) );

	if ( empty( $site_description ) )
    {
        $ds = array();
        if ( ! empty( $page_title ) ) $ds[] = $page_title;
        if ( $op != "main" ) $ds[] = $module_info['funcs'][$op]['func_custom_name'];
        $ds[] = $module_info['custom_title'];
        $ds[] = $client_info['selfurl'];
        $site_description = implode( " - ", $ds );
    }
	elseif ( $site_description == 'no' )
	{
		$site_description = '';
	}

	if ( ! empty( $site_description ) )
	{
		$return .= "<meta name=\"description\" content=\"" . strip_tags( $site_description ) . "\" />\n";
	}

	$kw = array();
	if( ! empty( $key_words ) )
	{
		if ( $key_words != 'no' )
		{
			$kw[] = $key_words;
		}
	}
	elseif( ! empty( $module_info['keywords'] ) )
	{
		$kw[] = $module_info['keywords'];
	}

	if( $home AND ! empty( $global_config['site_keywords'] ) )
	{
		$kw[] = $global_config['site_keywords'];
	}

	if( ! empty( $kw ) )
	{
		$kw = array_unique( $kw );
		$key_words = implode( ",", $kw );
		$key_words = preg_replace( array( "/[ ]*\,[ ]+/", "/[\,]+/" ), array( ", ", ", " ), $key_words );
		$key_words = nv_strtolower( strip_tags( $key_words ) );
		$return .= "<meta name=\"keywords\" content=\"" . $key_words . "\" />\n";
		$return .= "<meta name=\"news_keywords\" content=\"" . $key_words . "\" />\n";
	}

	$return .= "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=" . $global_config['site_charset'] . "\" />\n";

	if( $global_config['idsite'] AND file_exists( NV_ROOTDIR . '/' . NV_DATADIR . '/site_' . $global_config['idsite'] . '_metatags.xml' ) )
	{
		$file_metatags = NV_ROOTDIR . '/' . NV_DATADIR . '/site_' . $global_config['idsite'] . '_metatags.xml';
	}
	else
	{
		$file_metatags = NV_ROOTDIR . '/' . NV_DATADIR . '/metatags.xml';
	}

	if( file_exists( $file_metatags ) )
	{
		$mt = file_get_contents( $file_metatags );
		$patters = array();
		$patters['/\{CONTENT\-LANGUAGE\}/'] = $lang_global['Content_Language'];
		$patters['/\{LANGUAGE\}/'] = $lang_global['LanguageName'];
		$patters['/\{SITE\_NAME\}/'] = $global_config['site_name'];
		$patters['/\{SITE\_EMAIL\}/'] = $global_config['site_email'];
		$mt = preg_replace( array_keys( $patters ), array_values( $patters ), $mt );
		$mt = preg_replace( '/\{(.*)\}/', '', $mt );
		$mt = simplexml_load_string( $mt );
		$mt = nv_object2array( $mt );

		if( $mt['meta_item'] )
		{
			if( isset( $mt['meta_item'][0] ) ) $metatags = $mt['meta_item'];
			else
				$metatags[] = $mt['meta_item'];
			foreach( $metatags as $meta )
			{
				if( ( $meta['group'] == "http-equiv" or $meta['group'] == "name" ) and preg_match( "/^[a-zA-Z0-9\-\_\.]+$/", $meta['value'] ) and preg_match( "/^([^\'\"]+)$/", ( string )$meta['content'] ) )
				{
					$return .= "<meta " . $meta['group'] . "=\"" . $meta['value'] . "\" content=\"" . $meta['content'] . "\" />\n";
				}
			}
		}
	}

	$return .= "<meta name=\"generator\" content=\"NukeViet v3.x\" />\n";
	if( defined( 'NV_IS_ADMIN' ) )
	{
		$return .= "<meta http-equiv=\"refresh\" content=\"" . $global_config['admin_check_pass_time'] . "\" />\n";
	}

	if( empty( $canonicalUrl ) ) $canonicalUrl = $client_info['selfurl'];

	if( substr( $canonicalUrl, 0, 4 ) != "http" )
	{
		if( substr( $canonicalUrl, 0, 1 ) != "/" ) $canonicalUrl = NV_BASE_SITEURL . $canonicalUrl;

		$canonicalUrl = NV_MY_DOMAIN . $canonicalUrl;
	}

	//Open Graph protocol http://ogp.me
	if( $global_config['metaTagsOgp'] )
	{
		if( empty( $meta_ogp['title'] ) ) $meta_ogp['title'] = $page_title;
		if( empty( $meta_ogp['description'] ) ) $meta_ogp['description'] = $site_description;
		if( empty( $meta_ogp['type'] ) ) $meta_ogp['type'] = 'WebPage';
		if( empty( $meta_ogp['url'] ) ) $meta_ogp['url'] = $canonicalUrl;
		$meta_ogp['site_name'] = $global_config['site_name'];
		foreach( $meta_ogp as $key => $value )
		{
			if( ! empty( $value ) )
			{
				$return .= "<meta property=\"og:" . $key . "\" content=\"" . $value . "\" />\n";
			}
		}
	}
	//tài khoản Google+
	if( $id_profile_googleplus == 0 )
	{
		$id_profile_googleplus = $module_info['gid'];
	}
	if( $id_profile_googleplus > 0 )
	{
		$dbgoogleplus = nv_db_cache( 'SELECT `gid`, `idprofile` FROM `' . $db_config['prefix'] . '_googleplus`', 'gid', 'seotools' );
		if( isset( $dbgoogleplus[$id_profile_googleplus]['idprofile'] ) )
		{
			$return .= "<link rel=\"author\" href=\"https://plus.google.com/" . $dbgoogleplus[$id_profile_googleplus]['idprofile'] . "/\" />\n";
		}
	}

	$return .= "<link rel=\"canonical\" href=\"" . $canonicalUrl . "\" />\n";
	return $return;
}

/**
 * nv_html_page_title()
 *
 * @return
 */
function nv_html_page_title()
{
	global $home, $module_info, $op, $global_config, $page_title;

	$replace = array( "\\", "/", ":", "*", "?", "\"", "<", ">", "|" );

	if( $home )
	{
		return "<title>" . nv_htmlspecialchars( str_replace( $replace, '', strip_tags( $global_config['site_name'] ) ) ) . "</title>\n";
	}
	else
	{
		if( ! isset( $global_config['pageTitleMode'] ) or empty( $global_config['pageTitleMode'] ) ) $global_config['pageTitleMode'] = "pagetitle " . NV_TITLEBAR_DEFIS . " sitename";

		if( empty( $page_title ) and ! preg_match( "/(funcname|modulename|sitename)/i", $global_config['pageTitleMode'] ) ) return "<title>" . nv_htmlspecialchars( str_replace( $replace, '', strip_tags( $module_info['funcs'][$op]['func_custom_name'] . " " . NV_TITLEBAR_DEFIS . " " . $module_info['custom_title'] ) ) ) . "</title>\n";

		$_title = preg_replace( array( "/pagetitle/i", "/funcname/i", "/modulename/i", "/sitename/i" ), array( $page_title, $module_info['funcs'][$op]['func_custom_name'], $module_info['custom_title'], $global_config['site_name'] ), $global_config['pageTitleMode'] );
		return "<title>" . nv_htmlspecialchars( str_replace( $replace, '', strip_tags( $_title ) ) ) . "</title>\n";
	}
}

/**
 * nv_html_css()
 *
 * @return
 */
function nv_html_css()
{
	global $module_info, $module_file;

	if( file_exists( NV_ROOTDIR . "/themes/" . $module_info['template'] . "/css/" . $module_file . ".css" ) )
	{
		return "<link rel=\"StyleSheet\" href=\"" . NV_BASE_SITEURL . "themes/" . $module_info['template'] . "/css/" . $module_file . ".css\" type=\"text/css\" />\n";
	}

	return "";
}

/**
 * nv_html_site_rss()
 *
 * @return
 */
function nv_html_site_rss()
{
	global $rss;

	$return = '';
	if( ! empty( $rss ) )
	{
		foreach( $rss as $rss_item )
		{
			$return .= "<link rel=\"alternate\" href=\"" . $rss_item['src'] . "\" title=\"" . strip_tags( $rss_item['title'] ) . "\" type=\"application/rss+xml\" />\n";
		}
	}

	return $return;
}

/**
 * nv_html_site_js()
 *
 * @return
 */
function nv_html_site_js()
{
	global $global_config, $module_info, $module_name, $module_file, $lang_global, $op, $client_info;

	$return = "<script type=\"text/javascript\" src=\"" . NV_BASE_SITEURL . "js/language/" . NV_LANG_INTERFACE . ".js\"></script>\n";
	$return .= "<script type=\"text/javascript\" src=\"" . NV_BASE_SITEURL . "js/jquery/jquery.min.js\"></script>\n";
	$return .= "<script type=\"text/javascript\">\n//<![CDATA[\n";
	$return .= "var nv_siteroot=\"" . NV_BASE_SITEURL . "\",nv_sitelang=\"" . NV_LANG_INTERFACE . "\",nv_name_variable=\"" . NV_NAME_VARIABLE . "\",nv_fc_variable=\"" . NV_OP_VARIABLE . "\",nv_lang_variable=\"" . NV_LANG_VARIABLE . "\",nv_module_name=\"" . $module_name . "\",nv_my_ofs=" . round( NV_SITE_TIMEZONE_OFFSET / 3600 ) . ",nv_my_abbr=\"" . nv_date( "T", NV_CURRENTTIME ) . "\",nv_cookie_prefix=\"" . $global_config['cookie_prefix'] . "\",nv_area_admin=0;\n";
	$return .= "//]]>\n</script>\n";
	$return .= "<script type=\"text/javascript\" src=\"" . NV_BASE_SITEURL . "js/global.js\"></script>\n";
	if( defined( 'NV_IS_ADMIN' ) )
	{
		$return .= "<script type=\"text/javascript\" src=\"" . NV_BASE_SITEURL . "js/admin.js\"></script>\n";
	}
	if( file_exists( NV_ROOTDIR . "/modules/" . $module_file . "/js/user.js" ) )
	{
		$return .= "<script type=\"text/javascript\" src=\"" . NV_BASE_SITEURL . "modules/" . $module_file . "/js/user.js\"></script>\n";
	}
	if( defined( 'NV_EDITOR' ) and nv_function_exists( 'nv_add_editor_js' ) )
	{
		$return .= nv_add_editor_js();
	}
	if( defined( 'NV_IS_DRAG_BLOCK' ) )
	{
		if( ! defined( 'SHADOWBOX' ) )
		{
			$return .= "<link type=\"text/css\" rel=\"Stylesheet\" href=\"" . NV_BASE_SITEURL . "js/shadowbox/shadowbox.css\" />\n";
			$return .= "<script type=\"text/javascript\" src=\"" . NV_BASE_SITEURL . "js/shadowbox/shadowbox.js\"></script>\n";
			$return .= "<script type=\"text/javascript\">Shadowbox.init();</script>";
			define( 'SHADOWBOX', true );
		}
		$return .= "<script type=\"text/javascript\" src=\"" . NV_BASE_SITEURL . "js/ui/jquery.ui.core.min.js\"></script>\n";
		$return .= "<script type=\"text/javascript\" src=\"" . NV_BASE_SITEURL . "js/ui/jquery.ui.sortable.min.js\"></script>\n";
		$return .= '<script type="text/javascript">
 			//<![CDATA[
					var blockredirect = "' . nv_base64_encode( $client_info['selfurl'] ) . '";
					$(function() {
						$("a.delblock").click(function(){
							var bid = $(this).attr("name");
							if (confirm("' . $lang_global['block_delete_confirm'] . '")){
								$.post("' . NV_BASE_ADMINURL . 'index.php?' . NV_NAME_VARIABLE . '=themes&' . NV_OP_VARIABLE . '=blocks_del", "bid="+bid, function(theResponse){
									alert(theResponse);
									window.location.href = "' . $client_info['selfurl'] . '";
								});
							}
						});

						$("a.outgroupblock").click(function(){
							var bid = $(this).attr("name");
							if (confirm("' . $lang_global['block_outgroup_confirm'] . '")){
								$.post("' . NV_BASE_ADMINURL . 'index.php?' . NV_NAME_VARIABLE . '=themes&' . NV_OP_VARIABLE . '=front_outgroup", "func_id=' . $module_info['funcs'][$op]['func_id'] . '&bid="+bid, function(theResponse){
									alert(theResponse);
								});
							}
						});

						$("a.block_content").click(function(){
							var bid = $(this).attr("name");
							var tag = $(this).attr("id");
							Shadowbox.open(
							{
								content : "<iframe src=\'' . NV_BASE_ADMINURL . 'index.php?' . NV_NAME_VARIABLE . '=themes&' . NV_OP_VARIABLE . '=block_content&selectthemes=' . $global_config['module_theme'] . '&tag="+tag+"&bid="+bid+"&blockredirect="+blockredirect+"\' style=\'width:780px;height:450px\'></iframe>",
								player : "html",
								height : 450,
								width : 780
							}
						 );
	 		});

	 		var func_id = ' . ( $module_info['funcs'][$op]['func_id'] ) . ';
	 		var post_order = false;
						$(".column").sortable({
							connectWith: \'.column\',
							opacity: 0.8,
							cursor: \'move\',
							receive: function(){
									post_order = true;
									var position = $(this).attr("id");
									var order = $(this).sortable("serialize");
									$.post("' . NV_BASE_ADMINURL . 'index.php?' . NV_NAME_VARIABLE . '=themes&' . NV_OP_VARIABLE . '=sort_order", order+"&position="+position+"&func_id="+func_id, function(theResponse){
										if(theResponse=="OK_"+func_id){
					 					$("div#toolbar>ul.info").html("<li><span style=\'color:#ff0000;padding-left:150px;font-weight:700;\'>' . $lang_global['blocks_saved'] . '</span></li>").fadeIn(1000);
										}
										else{
											alert("' . $lang_global['blocks_saved_error'] . '");
										}
									});
							},
							stop: function() {
								if(post_order == false){
									var order = $(this).sortable("serialize");
									$.post("' . NV_BASE_ADMINURL . 'index.php?' . NV_NAME_VARIABLE . '=themes&' . NV_OP_VARIABLE . '=sort_order", order+"&func_id="+func_id, function(theResponse){
										if(theResponse=="OK_"+func_id){
					 					$("div#toolbar>ul.info").html("<span style=\'color:#ff0000;padding-left:150px;font-weight:700;\'>' . $lang_global['blocks_saved'] . '</span>").fadeIn(1000);
										}
										else{
											alert("' . $lang_global['blocks_saved_error'] . '");
										}
									});
								}
							}
						});
						$(".column").disableSelection();
					});
					//]]>
					</script>';
	}
	return $return;
}

/**
 * nv_admin_menu()
 *
 * @return
 */
function nv_admin_menu()
{
	global $lang_global, $admin_info, $module_info, $module_name, $db, $my_head;

	$block_theme = "default";

	$xtpl = new XTemplate( "admin_toolbar.tpl", NV_ROOTDIR . "/themes/" . $block_theme . "/system" );
	$xtpl->assign( 'GLANG', $lang_global );
	$xtpl->assign( 'ADMIN_INFO', $admin_info );
	$xtpl->assign( 'NV_BASE_SITEURL', NV_BASE_SITEURL );
	$xtpl->assign( 'NV_ADMINDIR', NV_ADMINDIR );
	$xtpl->assign( 'URL_AUTHOR', NV_BASE_SITEURL . NV_ADMINDIR . "/index.php?" . NV_NAME_VARIABLE . "=authors&amp;id=" . $admin_info['admin_id'] );

	if( defined( 'NV_IS_SPADMIN' ) )
	{
		if( ! defined( 'SHADOWBOX' ) )
		{
			$my_head .= "<link type=\"text/css\" rel=\"Stylesheet\" href=\"" . NV_BASE_SITEURL . "js/shadowbox/shadowbox.css\" />\n";
			$my_head .= "<script type=\"text/javascript\" src=\"" . NV_BASE_SITEURL . "js/shadowbox/shadowbox.js\"></script>\n";
			$my_head .= "<script type=\"text/javascript\">Shadowbox.init();</script>";
			define( 'SHADOWBOX', true );
		}

		$new_drag_block = ( defined( 'NV_IS_DRAG_BLOCK' ) ) ? 0 : 1;
		$lang_drag_block = ( $new_drag_block ) ? $lang_global['drag_block'] : $lang_global['no_drag_block'];

		$xtpl->assign( 'URL_DBLOCK', NV_BASE_SITEURL . "index.php?" . NV_LANG_VARIABLE . "=" . NV_LANG_DATA . "&amp;" . NV_NAME_VARIABLE . "=" . $module_name . "&amp;drag_block=" . $new_drag_block );
		$xtpl->assign( 'LANG_DBLOCK', $lang_drag_block );

		foreach( $db->query_strs as $key => $field )
		{
			$data = array(
				"class" => ( $key % 2 ) ? " highlight" : " normal",
				"imgsrc" => ( $field[1] ) ? NV_BASE_SITEURL . "themes/" . $block_theme . "/images/icons/good.png" : NV_BASE_SITEURL . "themes/" . $block_theme . "/images/icons/bad.png",
				"imgalt" => ( $field[1] ) ? $lang_global['ok'] : $lang_global['fail'],
				"queries" => nv_htmlspecialchars( $field[0] )
			);
			$xtpl->assign( 'DATA', $data );
			$xtpl->parse( 'main.is_spadadmin3.queries' );
		}

		$xtpl->parse( 'main.is_spadadmin' );
		$xtpl->parse( 'main.is_spadadmin2' );
		$xtpl->parse( 'main.is_spadadmin3' );
	}

	if( defined( 'NV_IS_MODADMIN' ) and ! empty( $module_info['admin_file'] ) )
	{
		$xtpl->assign( 'URL_MODULE', NV_BASE_SITEURL . NV_ADMINDIR . "/index.php?" . NV_NAME_VARIABLE . "=" . $module_name );
		$xtpl->parse( 'main.is_modadmin' );
	}

	$xtpl->parse( 'main' );
	return $xtpl->text( 'main' );
}

/**
 * nv_groups_list_pub()
 *
 * @return
 */
function nv_groups_list_pub()
{
	global $db, $db_config, $global_config;

	$query = "SELECT `group_id`, `title`, `exp_time`, `public` FROM `" . $db_config['dbsystem'] . "`.`" . NV_GROUPS_GLOBALTABLE . "` WHERE `act`=1 AND (`idsite` = " . $global_config['idsite'] . " OR (`idsite` =0 AND `siteus` = 1)) ORDER BY `idsite`, `weight`";
	$list = nv_db_cache( $query, '', 'users' );

	if( empty( $list ) ) return array();

	$groups = array();
	$reload = array();
	for( $i = 0, $count = sizeof( $list ); $i < $count; ++$i )
	{
		if( $list[$i]['exp_time'] != 0 and $list[$i]['exp_time'] <= NV_CURRENTTIME )
		{
			$reload[] = $list[$i]['group_id'];
		}
		elseif( $list[$i]['public'] )
		{
			$groups[$list[$i]['group_id']] = $list[$i]['title'];
		}
	}

	if( $reload )
	{
		$sql = "UPDATE `" . $db_config['dbsystem'] . "`.`" . NV_GROUPS_GLOBALTABLE . "` SET `act`='0' WHERE `group_id` IN (" . implode( ",", $reload ) . ")";
		$db->sql_query( $sql );
		nv_del_moduleCache( 'users' );
	}

	return $groups;
}

?>