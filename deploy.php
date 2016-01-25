<?php 
/*
 * Deploy Github to remote server 
 * Author: Rex Bengil
 * Email: centraleffects@yahoo.com
 * 
 * Modify the script according to your needs.
 * This can be implemented using Github Webhooks: https://github.com/your-github-username/your-github-repo/settings/hooks
*/

ignore_user_abort(true); 
set_time_limit(0);

$data = json_decode($_REQUEST['payload']);
$repo_owner = "username of repo owner";
$repo_name = "name of repo";
//change this to whatever branch you want to deploy in your remote server
$branch = "dev";

//personal access token
//obtain it here: https://github.com/settings/tokens
$token = "a791f0e89dda8b84aa894c6574970cc49740321";
//github username
$username ='change this with your github username';

//use your personal access token as a password if you dont want to use your github password
$password = 'a791f0e89dda8b84aa894c6574970cc49740321';

$from_email = "git@mywebsite-on-production.com";
//Will be notified if someone will push to that repo
$to_email = "your_email@your-domain.com";


$msg = "";
if($data->ref == "refs/heads/".$branch){

   if(!empty($data->commits)){
    
      foreach($data->commits as $commit){


         $msg .= "Author: ".$commit->author->name."\nMessage: ".$commit->message."\n";
         echo "Author: ".$commit->author->name."\nMessage: ".$commit->message."<br>";

         if(!empty($commit->added)){
          
            foreach($commit->added as $file){

               addUpdateFile($repo_owner,$repo_name,$branch,$file,$username,$password);

               $msg .="Added: ".$file."\n";
               echo "Added: ".$file."<br>";
            }
           
         }

         if(!empty($commit->removed)){

            $dir_array = array();

            foreach($commit->removed as $file){


               $dirname = dirname($file);
                if(file_exists($file)){
                  unlink($file);
                }
                if(!in_array($dirname, $dir_array)){
                  array_push($dir_array, $dirname);
                }

               $msg .="Removed: ".$file."\n";
               echo "Removed: ".$file."<br>";
            }

           if(!empty($dir_array)){
              foreach($dir_array as $dir){
                $y = deleteDirectory($dir);
                if($y<>""){ $logmsg .= "[".date('d-m-Y h:i:s')."] - $y"; }
              }
            }
         }

         if(!empty($commit->modified)){

            foreach($commit->modified as $file){

               addUpdateFile($repo_owner,$repo_name,$branch,$file,$username,$password);
               $msg .="Modified: ".$file."\n";
               echo "Modified: ".$file."<br>";
            }
            
         }
         $msg .= "------------\n";

        ob_flush();
       //end of commit
      }

     
      $subject = "Deployed from ".$branch.' branch on '.date('Y-m-d h:i:s');
      $txt = "Details below \r\n".$msg."\r\n\r\n".$_REQUEST['payload'];
      $headers = "From: ".$from_email . "\r\n";

      mail($to_email,$subject,$txt,$headers);
   }

}
//end of checking payload content






/********************************************
 *  FUNCTIONS NEEDED
 ********************************************/

function addUpdateFile($repo_owner,$repo_name,$branch,$path,$username,$password)
  {
      $url = 'https://raw.githubusercontent.com/'.$repo_owner.'/'.$repo_name.'/'.$branch.'/'.$path;


      //strip "upload/" for dev server only
        if(substr($path, 0,7) == "upload/"){
          $path = substr($path, 7,strlen($path)-7);
        }else{
           //this is outside upload/ then, skip.
          echo "[skipped] Outside: ".$path."<br>";
           continue;
        }
      //-----------------------------------------
                  
      $dirname = dirname($path);

      $chdir = is_dir($dirname);
      
      if($chdir == false){
        if(make_directory($dirname)){
          //$logmsg .= "[".date('d-m-Y h:i:s')."] - Created new directory '$dirname'\n";
        } else {
          //$logmsg .= "[".date('d-m-Y h:i:s')."] - Failed to create directory '$dirname'\n";
        }
      }

      if(file_exists($path)){ unlink($path); }

      $fp = fopen($path, 'w');
      $ch = curl_init($url);
      $User_Agent = 'Mozilla/5.0 (X11; Linux i686) AppleWebKit/537.31 (KHTML, like Gecko) Chrome/26.0.1410.43 Safari/537.31';
      $cookies = 'CookieName1=Value;CookieName2=$password';
      curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
      curl_setopt($ch, CURLOPT_USERPWD, $username.':'.$password); 
      curl_setopt($ch, CURLOPT_USERAGENT, $User_Agent);
      curl_setopt($ch, CURLOPT_COOKIE, $cookies);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
      curl_setopt ($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
      curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
      curl_setopt($ch, CURLOPT_FILE, $fp);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
      curl_exec($ch);
      curl_close($ch);

  }
  



function is_dir_empty($dir) {
  if (!is_readable($dir)) return NULL; 
  return (count(scandir($dir)) == 2);
}
function deleteDirectory($path) {
    $newpath = "";
  $dir = explode('/',$path);
  $flag = "";
  ksort($dir);
  $newdir = $dir;
  foreach($dir as $d){
    $x = count($newdir);
    if(is_array($newdir) and $x>1){ 
      $newpath = implode('/', $newdir);
    }else{
      $newpath = $d;
    }
    if(is_dir($newpath)){
      if(is_dir_empty($newpath)){
        $y = rmdir($newpath);
        if($y){
          $flag.= "Dir Deleted : ".$newpath."\n";
        }else{
          $flag.= "Failed to Deleted: ".$newpath."\n";
        }
      }
    }
    if( $x>1){
      $newdir = array_pop($newdir); 
    } 
  }
  return $flag;
}

function make_directory($path){

  $newpath = "";
  $dir = explode('/',$path);
  $a=0;
  foreach($dir as $d){
    if($a==0){
      $newpath .= $d;
    }else{
      $newpath .= '/'.$d;
    }
    
    if(!is_dir($newpath)){
      if(mkdir($newpath)){
        chmod($newpath, 0775);
      }else{
        $fp = fopen('deploy_log', 'a');
        fwrite($fp, "\n Failed to write: ".$newpath);
        fclose($fp);
        return false;
      }
    }else{
      chmod($newpath, 0775);
    }
   $a++;    
  }
  return true;
}
?>
