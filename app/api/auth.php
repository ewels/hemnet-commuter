<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/*
 * Really crappy authentication system
 * Please don't trust this for anything important
 */

/////////
// CALLED DIRECTLY - API usage
/////////
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {

    require_once('_common_api.php');
    echo json_encode(['has_auth'=> check_auth_token()], JSON_PRETTY_PRINT);

}
