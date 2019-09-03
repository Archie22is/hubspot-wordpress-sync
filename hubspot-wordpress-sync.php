<?php 
/**
 * Plugin Name: HubSpot and WordPress Sync
 * Plugin URI: http://archie.makuwa.co.za
 * Description: HubSpot CRM and WordPress Sync Plugin
 * Version: 1.0.0
 * Author: Archie Makuwa (Archie @TheLobableType)
 * Author URI: http://archie.makuwa.co.uk
 * License: GPL v3
 * Text Domain: hubspotwpsync
 */

/**
 * No funny business. If this file is called directly, abort.
 * @author WordPress
 * 
 */
if ( ! defined( 'WPINC' ) ) {
    die;
}


/**
 * Get WordPress User  
 * (currently only works when user is logged in... an be extended)
 * @author Archie M
 * 
 */
class ManageWordPressUser {

    public function __construct(){
        add_action( 'plugins_loaded', array( $this, 'check_if_user_logged_in' ) );
    }

    public function check_if_user_logged_in(){
        if ( is_user_logged_in() ){

            /**
             * HubSpot Tracking some shit - can definitely come in handy (not implemented here) 
             * @author HubSpot
             */
            /*
            $hubspotutk = $_COOKIE['hubspotutk']; // grab the cookie from the visitors browser.
            $ipaddress = $_SERVER['REMOTE_ADDR']; // IP address.
            $hs_context = array(
                    'hutk'      => $hubspotutk,
                    'ipAddress' => $ipaddress,
                    'pageUrl'   => $_SERVER['request_uri'],
                    'pageName'  => 'Checkout'
                );
            $hs_context_json = json_encode( $hs_context );
            */
           
       
            /**
             * API key MUST be stored in a safe and location and encrypted
             */ 
            $url = "https://api.hubapi.com/contacts/v1/contact/?hapikey=XXXXXXXXXXXXXXXXXXXXXXXXXXX";

            $current_user = wp_get_current_user();
            //var_dump($current_user);

            $fields['properties'][] = array( "property" => "email", "value" => $current_user->user_email );
            $fields['properties'][] = array( "property" => "firstname", "value" => $current_user->user_firstname );
            $fields['properties'][] = array( "property" => "lastname", "value" => $current_user->user_lastname  );
            // You can get the company from  WooCommerce's Company Field or create your very own Company meta field

            $response = wp_remote_post($url, 
                array(
                    'method'            => 'POST',
                    'timeout'           => 1000,
                    'headers'           => array(
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ),
                    //'headers'         => 'Content-Type: application/x-www-form-urlencoded',
                    'httpversion'       => '1.0',
                    'sslverify'         => false,
                    'body'              => json_encode( $fields ), 
                )
            );

            // Check response codes 
            $response_code      = wp_remote_retrieve_response_code( $response );
	        $response_message   = wp_remote_retrieve_response_message( $response );


            // Control errors
            $file = plugin_dir_path( __FILE__ ) . '/log/log.txt';
            
            if ( 200 != $response_code && ! empty( $response_message ) ) {
                //$error_message = $response->get_error_message();
                $error = "Something went wrong.";
                file_put_contents( $file, $error);
            } else {
                //echo 'Response:<pre>';
                //print_r( $response );
                //echo '</pre>';
                $now = new DateTime();
                $time_now = $now->format('Y-m-d H:i:s');    // MySQL datetime format
                //echo $now->getTimestamp();     
                file_put_contents( $file, "Success " . $time_now );
            } 

        }

    }

}
$ManageWordPressUser_plugin = new ManageWordPressUser();



/**
 * Create Users in WordPress from HubSpot CRM (if they don't exist)
 * @author Archie M
 * 
 */
class CreateUsersFromHubSpotUsers {

    public function __construct(){
        add_action( 'plugins_loaded', array( $this, 'create_users' ) );
    }

    public function create_users() {

        /**
         * API key MUST be stored in a safe and location and encrypted
         **/ 
        $url = "https://api.hubapi.com/contacts/v1/lists/all/contacts/all?hapikey=XXXXXXXXXXXXXXXXXXXXXXXXXXX";
        
        global $wp_version;

        $args = array(
            'timeout'     => 5,
            'redirection' => 5,
            'httpversion' => '1.0',
            'user-agent'  => 'WordPress/' . $wp_version . '; ' . home_url(),
            'blocking'    => true,
            //'headers'     => array(),
            'cookies'     => array(),
            'body'        => null,
            'compress'    => false,
            'decompress'  => true,
            'sslverify'   => true,
            'stream'      => false,
            'filename'    => null
        );

        //$feed_response = wp_remote_get( $url, $args ); 
        $response = wp_remote_get( $url, $args );
    
        if( !is_wp_error( $response ) ) {
            
            $response_body = json_decode($response['body']);    
            $decoded_response_body = json_decode( json_encode($response_body) );
            
            if( isset($decoded_response_body->contacts) ) {

                foreach( $decoded_response_body->contacts as $contact) {
    
                    // get user details
                    if( !empty($contact->properties->firstname->value) ): 
                        $first_name = $contact->properties->firstname->value;; 
                    else: 
                        $fist_name = '';
                    endif;

                    if( !empty($contact->properties->lastname->value) ): 
                        $last_name = $contact->properties->lastname->value;; 
                    else: 
                        $last_name = '';
                    endif;

                    if( !empty($contact->properties->company->value) ): 
                        $company_name = $contact->properties->company->value;; 
                    else: 
                        $company_name = '';
                    endif;


        
                    $identities = $contact->{'identity-profiles'}[0]->identities;
                    foreach ( $identities as $key => $value ){
                        if ( strtolower($value->type) === 'email' ){
                            $contact_email = $value->value;
                        }
                    }
    
                    /**
                     * Create users captured in HubSpot on the WordPress CMS
                     * @author Archie M
                     * 
                     */
                    // Get useful data
                    $username = sanitize_text_field( wp_unslash( $contact_email ));
                    $email = sanitize_email( $contact_email );
                    $first_name = sanitize_text_field( wp_unslash( $first_name ));
                    $last_name = sanitize_text_field( wp_unslash( $last_name ));
                    $company_name = sanitize_text_field( wp_unslash( $company_name ));
                    $password = wp_generate_password( 10, true, true );
                    $hashed_pwd = wp_hash_password($password);
        
                    $user_id = username_exists( $username );
                    if ( !$user_id && email_exists($email) == false ) {
                        
                        //$user_id = wp_create_user( $username, $password, $email );
                        $userdata = array(
                            'user_login'  	=>  $username,
                            'first_name'    =>  $first_name,
                            'last_name'		=>	$last_name,
                            'user_pass'   	=>  $hashed_pwd
                        );
                        $user_id = wp_insert_user( $userdata ) ;
                        
                        // if no error
                        if( !is_wp_error($user_id) ) {
                            $user = get_user_by( 'id', $user_id );
                            $user->set_role( 'customer' );

                            // Update custom meta fields (in this case, the WooCommerce Company Name)
                            update_user_meta( $user_id, 'billing_company', $company_name, '' );

        
                            // mail admin 
                            $to = "iamdrinking@thepub.co.za";
                            $subject = "Created users from HubSpot";
                            $headers = array('Content-Type: text/html; charset=UTF-8');
                            $message = "The following users have been created in WordPress" . "\r\n";
                            $message .= "First Name: " . $first_name . "\r\n";
                            $message .= "Last Name " . $last_name . "\r\n";
                            $message .= "Company Name " . $company_name . "\r\n";
                            $message .= "Email Address " . $contact_email . "\r\n";
                            $attachments = "";
                            
                            wp_mail( $to, $subject, $message, $headers, $attachments );
        
                        }
                    }
        
                }
            }
    
        }

    }

}

$CreateUsersFromHubSpotUsers = new CreateUsersFromHubSpotUsers(); 

