<?php
namespace WpLdp;

/**
 * Class handling everything related to settings page and available options inside them
 **/
if (!class_exists('\WpLdp\WpLdpApi')) {
    class WpLdpApi {
        /**
         * __construct - Class default constructor
         *
         * @return {WpLdpSettings}  Instance of the WpLdpSettings Class
         */
        public function __construct() {
            add_filter( 'rest_url_prefix', array( $this, 'define_api_slug' ) );
            add_action( 'rest_api_init', function() {
                register_rest_route( 'ldp/v1', '/schema/', array(
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => array( $this, 'get_api_definition' ),
                ), true );

                register_rest_route( 'ldp/v1', '/sites/', array(
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => array( $this, 'get_sites_list' ),
                ) );

                register_rest_route( 'ldp/v1', '/(?P[\s]+)/', array(
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => array( $this, 'get_resources_from_container' ),
                ) );

                register_rest_route( 'ldp/v1', '/(?P[\s]+)/(?P[\s]+)', array(
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => array( $this, 'get_resource' ),
                ) );
            });
        }

        public function get_api_definition( \WP_REST_Request $request, \WP_REST_Response $response = null ) {
            $query = new \WP_Query( array(
               'post_type' => 'ldp_resource',
               'posts_per_page' => -1 )
             );
            $array = [];

            $posts = $query->get_posts();

            $result = '
            {
                "@context": "' . get_option('ldp_context', 'http://lov.okfn.org/dataset/lov/context') . '",
                "@graph": [ {
                    "@id" : "http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '",
                    "@type" : "http://www.w3.org/ns/ldp#BasicContainer",
                    "http://www.w3.org/ns/ldp#contains" : [';

            foreach ($posts as $post ) {
                $values = get_the_terms($post->ID, 'ldp_container');
                if (empty($values[0])) {
                  $value = reset($values);
                } else {
                  $value = $values[0];
                }
                $termMeta = get_option("ldp_container_$value->term_id");
                $rdfType = isset($termMeta["ldp_rdf_type"]) ? $termMeta["ldp_rdf_type"] : null;

                if($rdfType != null){
                    if(array_key_exists($rdfType,$array)){
                      $array[$rdfType]['value']++;
                    }
                    else{
                        $array[$rdfType]['value']=1;
                        $array[$rdfType]['id']=explode(':',$rdfType)[1];
                    }
                }
            }

            $i = 0;
            foreach ( $array as $key => $value ) {
                $result .= "            {\n";
                $result .= "                \"@id\" : \"http://" .$_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'].$value['id']."\",\n";
                $result .= "                \"@type\" : \"$key\",\n";
                $result .= "                \"@count\" : ".$value['value']."\n";
                if ( $i +1 == sizeof($array) ) {
                    $result .= "            }\n";
                } else {
                    $result .= "            },\n";
                }
                $i++;
            }
            $result .=   "]";
            $result .=   "}]";
            $result .=   "}";

            return rest_ensure_response( json_decode( $result ) );
        }

        public function get_resources_from_container() {

        }

        public function get_resource() {

        }

        public function define_api_slug( $slug ) {
            return 'api';
        }

        public function get_sites_list(  \WP_REST_Request $request, \WP_REST_Response $response = null ) {
            $terms = get_terms( array(
              'taxonomy' => 'ldp_site',
              'hide_empty' => false,
            ) );

            foreach ( $terms as $term ){
                $possibleUrl = get_term_meta( $term->term_id, "ldp_site_url", true );
                if ( $possibleUrl ) {
                    $ldpSiteUrls[] =$possibleUrl;
                }
            }

            $outputs = array();
            foreach ($ldpSiteUrls as $ldpSiteUrl){
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $ldpSiteUrl.'/ldp/');
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER,array('Content-Type: application/ld+json', 'Accept: application/ld+json'));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $outputs[$ldpSiteUrl.'/ldp/']['data'] = curl_exec($ch);
                $outputs[$ldpSiteUrl.'/ldp/']['code'] = curl_getinfo($ch)['http_code'];
            }

            $sites = array();
            foreach ($outputs as $output){
                if($output['code'] == 200){
                    $sites[] = json_decode( $output['data'] );
                }
            }

            return rest_ensure_response( $sites );
        }
    }
    // Instanciating the settings page object
    $wpLdpApi = new WpLdpApi();
} else {
    exit ('Class WpLdpApi already exists');
}
