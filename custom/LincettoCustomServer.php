<?php

define('WOOCOMMERCE_CONSTRIV_LIC_SPECIAL_SECRET_KEY', '56e411bd354383.90052962');
define('WOOCOMMERCE_CONSTRIV_LIC_SERVER_URL', 'http://lincetto.me');
define('WOOCOMMERCE_CONSTRIV_LIC_ITEM_REF', 'Woocommerce Consorzio Triveneto');

# No need for the template engine
define( 'WP_USE_THEMES', false );
# Load WordPress Core
require_once( __DIR__ . '/../../wp-load.php' );

class LincettoCustomServer extends Wpup_UpdateServer {

    protected function filterMetadata($meta, $request) {
        $meta = parent::filterMetadata($meta, $request);
 
        //Include license information in the update metadata. This saves an HTTP request
        //or two since the plugin doesn't need to explicitly fetch license details.
        $license = $request->license;
        if ( $license && $license !== null ) {
            $meta['license_status'] = $license->status;
            $meta['license_expiry'] = $license->date_expiry;            
        }
 
        //Only include the download URL if the license is valid.
        if ( $license && is_object($license) && $license->status == 'active' ) {
            //Append the license key or to the download URL.
            $args = array( 'license_key' => $request->param('license_key') );
            $meta['download_url'] = self::addQueryArg($args, $meta['download_url']);
        } else {
            //No license = no download link.
            unset($meta['download_url']);
        }
 
        return $meta;
    }

    protected function initRequest($query = null, $headers = null) {
        $request = parent::initRequest($query, $headers);
 
        //Load the license, if any.
        $license = false;
        if ( $request->param('license_key') ) {
            $licenseCheck = $this->getLicenseStatus($request->param('license_key'));
            if ( $licenseCheck !== false ) {
                $license = $licenseCheck;
            }
        }
 
        $request->license = $license;
        return $request;
    }

    protected function checkAuthorization($request) {
        parent::checkAuthorization($request);
 
        //Prevent download if the user doesn't have a valid license.
        $license = $request->license;
        if ( $request->action === 'download' && ! ($license && is_object($license) && $license->status == 'active') ) {
            if ( !isset($license) ) {
                $message = 'You must provide a license key to download this plugin.';
            } else {
                $message = 'Sorry, your license is not valid. (' . ($license ? $license->status : 'false') . ')';
            }
            $this->exitWithError($message, 403);
        }
    }

    private function getLicenseStatus($license_key) {
        try {
            $api_params = array(
                'slm_action' => 'slm_check',
                'secret_key' => WOOCOMMERCE_CONSTRIV_LIC_SPECIAL_SECRET_KEY,
                'license_key' => $license_key,
                'registered_domain' => '***UPDATE_SERVER***',
                'item_reference' => urlencode(WOOCOMMERCE_CONSTRIV_LIC_ITEM_REF),
            );

            $query = esc_url_raw(add_query_arg($api_params, WOOCOMMERCE_CONSTRIV_LIC_SERVER_URL));
            $response = wp_remote_get($query, array('timeout' => 20, 'sslverify' => false));
            
            $license_data = json_decode(wp_remote_retrieve_body($response));     
                        
            if ($license_data->result == 'success') {
                return $license_data;
            } else
                return false;
        } catch (Exception $err) {
            return false;
        }       
    }

}