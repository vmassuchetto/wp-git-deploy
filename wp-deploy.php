<?php
/*
* Plugin Name: WP-Deploy
* Plugin URI: http://github.com/vmassuchetto/wp-deploy
* Description: Deploy websites via the admin bar using Git
* Version: 0.01
* Author: Leo Germani, Vinicius Massuchetto
* Author URI: http://github.com/vmassuchetto/wp-deploy
*/
class WP_Deploy {
    
    var $custom_vars;

    function WP_Deploy() {

        if ( !is_super_admin() || !is_admin_bar_showing() )
            return;
         
        $this->custom_vars = array(
            'wp_deploy_update_info',
            'wp_deploy_reset_branch'
        );

        add_action( 'query_vars', array( $this, 'query_vars' ) );
        add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 999 );
        add_action( 'wp', array( $this, 'wp' ) );

    }

    function wp() {
        
        foreach ( $this->custom_vars as $var ) {
            if ( $arg = get_query_var( $var ) ) {
                $func = array( $this, preg_replace( '/^wp_deploy_/', '', $var ) );
                if ( is_callable( $func ) )
                    call_user_func( $func , $arg );
            }
        }

    }

    function query_vars( $vars ) {
        return $this->custom_vars + $vars;
    }

    function admin_bar_menu() {

        global $wp, $wp_admin_bar;

        $cleaned_url = $this->cleaned_url();

        $wp_admin_bar->add_menu( array(
            'id' => 'wp_deploy',
            'title' => __( 'Deploy', 'wp-deploy' ),
            'href' => false ) );

        $wp_admin_bar->add_menu( array(
            'id' => 'wp_deploy_update_info',
            'parent' => 'wp_deploy',
            'title' => __( 'Update branches', 'wp-deploy' ),
            'href' => add_query_arg(
                array( 'wp_deploy_update_info' => true ),
                $cleaned_url ) ) );
        
        foreach ( get_option( 'wp_deploy_branches' ) as $branch => $title ) {
            $wp_admin_bar->add_menu( array(
                'id' => 'wp_deploy_branch_' . $branch,
                'parent' => 'wp_deploy',
                'title' => $title,
                'href' => add_query_arg(
                    array( 'wp_deploy_reset_branch' => $branch ),
                    $cleaned_url ) ) );
        }

    }
    
    function cleaned_url() {

        if ( $cleaned_url = wp_cache_get( 'wp_deploy_cleaned_url' ) )
            return $cleaned_url;

        global $wp;

        $cleaned_url = home_url( $wp->request );
        foreach ( $this->custom_vars as $var ) {
            $cleaned_url = remove_query_arg( $var, $cleaned_url );
        }

        wp_cache_set( 'wp_deploy_cleaned_url', $cleaned_url );

        return $cleaned_url;
    }

    function update_info() {

        exec( 'cd ' . ABSPATH );
        exec( 'git fetch -p' );
        exec( 'git branch --all --list --no-color \
            | grep -v "HEAD" \
            | sed -e "s/^\* //g" \
            | sed -e "s/^  //g" \
            | sort -u ', $_branches );
        $current_branch = exec( 'git branch \
            | grep "^*" \
            | sed -e "s/^\* //g"' );

        $branches = array();
        foreach ( $_branches as $branch ) {
            $revision = exec( 'git show ' . $branch . ' \
                | head -1 \
                | sed -e "s/[^ ]* \(.\{7\}\).*/\1/g"' );
            $branch = preg_replace( '#.*/(.*)#', '\1', $branch );
            $prefix = $branch == $current_branch ? '* ' : '';
            $branches[ $branch ] = $prefix . $branch
                . '&nbsp;<span>' . trim( $revision ) . '</span>';
        }
        
        ksort( $branches );
        update_option( 'wp_deploy_branches', $branches );

    }

    function reset_branch( $branch ) {
        $branch = preg_replace('/^\*/', '', $branch);
        exec( 'git reset --hard HEAD' );
        exec( 'git checkout ' . $branch );
        exec( 'git pull' );
        $this->update_info();
    }

}

function wp_deploy_init() {
    new WP_Deploy();
}
add_action( 'plugins_loaded', 'wp_deploy_init' );


?>
