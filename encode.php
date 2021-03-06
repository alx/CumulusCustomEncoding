<?php
setlocale(LC_ALL, 'fr_FR');

// Include required files
include_once ('/var/www/cumulusclips/cc-core/config/bootstrap.php');
App::LoadClass ('Video');
Plugin::Trigger ('encode.start');


// Establish page variables, objects, arrays, etc
if (!isset ($argv[1]) || !preg_match ('/--video=(.*)$/i', $argv[1], $arg_matches)) exit();
$video_id = $arg_matches[1];
Plugin::Trigger ('encode.parse');
$ffmpeg_path = Settings::Get ('ffmpeg');
$qt_faststart_path = DOC_ROOT . '/cc-core/system/bin/qtfaststart';


// Set MySQL wait_timeout to 10 hours to prevent 'MySQL server has gone away' errors
$db->Query ("SET @@session.wait_timeout=36000");


// Debug Log
if ($config->debug_conversion) {
    App::Log (CONVERSION_LOG, "\n\n### Converter Called...");
    App::Log (CONVERSION_LOG, "Values passed to encoder:\n" . print_r ($argv, TRUE));
}




try {

    /////////////////////////////////////////////////////////////
    //                        STEP 1                           //
    //               Validate Requested Video                  //
    /////////////////////////////////////////////////////////////


    // Debug Log
    $config->debug_conversion ? App::Log (CONVERSION_LOG, 'Validating requested video...') : null;

    ### Validate requested video
    $video = new Video ($video_id);
    if (!Video::Exist(array ('video_id' => $video_id, 'status' => 'pending conversion'))) throw new Exception ("An invalid video was passed to the video encoder.");




    // Debug Log
    $config->debug_conversion ? App::Log (CONVERSION_LOG, 'Establishing variables...') : null;

    ### Retrieve video information
    $video->Update (array ('status' => 'processing'));
    $debug_log = LOG . '/' . $video->filename . '.log';
    $raw_video = UPLOAD_PATH . '/temp/' . $video->filename . '.' . $video->original_extension;
    $flv = UPLOAD_PATH . '/flv/' . $video->filename . '.mp4';
    $mobile_temp = UPLOAD_PATH . '/mobile/' . $video->filename . '_temp.mp4';
    $mobile = UPLOAD_PATH . '/mobile/' . $video->filename . '.mp4';
    $thumb = UPLOAD_PATH . '/thumbs/' . $video->filename . '.jpg';
    Plugin::Trigger ('encode.load_video');




    // Debug Log
    $config->debug_conversion ? App::Log (CONVERSION_LOG, 'Verifying raw video exists...') : null;

    ### Verify Raw Video Exists
    if (!file_exists ($raw_video)) throw new Exception ("The raw video file does not exists. The id of the video is: $video->video_id");




    // Debug Log
    $config->debug_conversion ? App::Log (CONVERSION_LOG, 'Verifying raw video was valid size...') : null;

    ### Verify Raw Video has valid file size
    // (Greater than min. 5KB, anything smaller is probably corrupted
    if (!filesize ($raw_video) > 1024*5) throw new Exception ("The raw video file is not a valid filesize. The id of the video is: $video->video_id");









    /////////////////////////////////////////////////////////////
    //                        STEP 2                           //
    //                Encode raw video to FLV                  //
    /////////////////////////////////////////////////////////////


    // Debug Log
    $config->debug_conversion ? App::Log (CONVERSION_LOG, "\nPreparing for: FLV Encoding...") : null;

    # Add overlay : http://stackoverflow.com/a/10920872
    $overlay = dirname(__FILE__) . "/watermark/watermark.png";
    ### Encode raw video to FLV
    $flv_command = "$ffmpeg_path -i " . escapeshellarg($raw_video) . " -i " . $overlay . " -filter_complex 'overlay=50:main_h-overlay_h-50' " . Settings::Get('flv_options') . " " . escapeshellarg($flv) . " >> " . escapeshellarg($debug_log) . " 2>&1";
    Plugin::Trigger ('encode.before_flv_encode');

    // Debug Log
    $log_msg = "\n\n\n\n==================================================================\n";
    $log_msg .= "FLV ENCODING\n";
    $log_msg .= "==================================================================\n\n";
    $log_msg .= "FLV Encoding Command: $flv_command";
    $log_msg .= "FLV Encoding Output:\n\n";
    $config->debug_conversion ? App::Log (CONVERSION_LOG, 'FLV Encoding Command: ' . $flv_command) : null;
    App::Log ($debug_log, $log_msg);

    ### Execute FLV encoding command
    exec ($flv_command);
    Plugin::Trigger ('encode.flv_encode');





    /////////////////////////////////////////////////////////////
    //                        STEP 3                           //
    //              Encode raw video to Mobile                 //
    /////////////////////////////////////////////////////////////


    // Debug Log
    $config->debug_conversion ? App::Log (CONVERSION_LOG, "\nPreparing for: Mobile Encoding...") : null;

    ### Encode raw video to Mobile
    $mobile_command = "$ffmpeg_path -i " . escapeshellarg($raw_video) . " " . Settings::Get('mobile_options') . " " . escapeshellarg($mobile_temp) . " >> " . escapeshellarg($debug_log) . " 2>&1";
    Plugin::Trigger ('encode.before_mobile_encode');

    // Debug Log
    $log_msg = "\n\n\n\n==================================================================\n";
    $log_msg .= "MOBILE ENCODING\n";
    $log_msg .= "==================================================================\n\n";
    $log_msg .= "Mobile Encoding Command: $mobile_command\n";
    $log_msg .= "Mobile Encoding Output:\n\n";
    $config->debug_conversion ? App::Log (CONVERSION_LOG, 'Mobile Encoding Command: ' . $mobile_command) : null;
    App::Log ($debug_log, $log_msg);

    ### Execute Mobile Encoding Command
    exec ($mobile_command);
    Plugin::Trigger ('encode.mobile_encode');








    /////////////////////////////////////////////////////////////
    //                        STEP 4                           //
    //            Shift Moov atom on Mobile video              //
    /////////////////////////////////////////////////////////////


    // Debug Log
    $config->debug_conversion ? App::Log (CONVERSION_LOG, "\nChecking qt-faststart permissions...") : null;

    if ((string) substr (sprintf ('%o', fileperms ($qt_faststart_path)), -4) != '0777') {
        try {
            Filesystem::Open();
            Filesystem::SetPermissions ($qt_faststart_path, 0777);
            Filesystem::Close();
        } catch (Exception $e) {
            throw new Exception ("Unable to update permissions for qt-faststart. Please make sure it ($qt_faststart_path) has 777 executeable permissions.\n\nAdditional information: " . $e->getMessage());
        }
    }



    // Debug Log
    $config->debug_conversion ? App::Log (CONVERSION_LOG, "\nShifting moov atom on Mobile video...") : null;

    ### Execute Faststart Command
    $faststart_command = "$qt_faststart_path " . escapeshellarg($mobile_temp) . " " . escapeshellarg($mobile) . " >> " . escapeshellarg($debug_log) . " 2>&1";
    Plugin::Trigger ('encode.before_faststart');

    // Debug Log
    $log_msg = "\n\n\n\n==================================================================\n";
    $log_msg .= "FASTSTART\n";
    $log_msg .= "==================================================================\n\n";
    $log_msg .= "Faststart Command: $faststart_command\n";
    $log_msg .= "Faststart Output:\n\n";
    $config->debug_conversion ? App::Log (CONVERSION_LOG, 'Faststart Command: ' . $faststart_command) : null;
    App::Log ($debug_log, $log_msg);

    ### Execute Faststart command
    exec ($faststart_command);
    Plugin::Trigger ('encode.faststart');









    /////////////////////////////////////////////////////////////
    //                        STEP 5                           //
    //                  Get Video Duration                     //
    /////////////////////////////////////////////////////////////


    // Debug Log
    $config->debug_conversion ? App::Log (CONVERSION_LOG, "\nRetrieving video duration...") : null;

    ### Retrieve duration of raw video file.
    $duration_cmd = "$ffmpeg_path -i " . escapeshellarg($raw_video) . " 2>&1 | grep Duration:";
    Plugin::Trigger ('encode.before_get_duration');
    exec ($duration_cmd, $duration_results);

    // Debug Log
    $config->debug_conversion ? App::Log (CONVERSION_LOG, "Duration command results:\n" . print_r ($duration_results, TRUE)) : null;




    $duration_results_cleaned = preg_replace ('/^\s*Duration:\s*/', '', $duration_results[0]);
    preg_match ('/^[0-9]{2}:[0-9]{2}:[0-9]{2}/', $duration_results_cleaned, $duration);
    $sec = Functions::DurationInSeconds ($duration[0]);

    // Debug Log
    $config->debug_conversion ? App::Log (CONVERSION_LOG, "Duration in Seconds: $sec") : null;




    // Calculate thumbnail position
    $thumb_position = round ($sec / 2);
    Plugin::Trigger ('encode.get_duration');

    // Debug Log
    $config->debug_conversion ? App::Log (CONVERSION_LOG, "Thumb Position: $thumb_position") : null;









    /////////////////////////////////////////////////////////////
    //                        STEP 6                           //
    //                Create Thumbnail Image                   //
    /////////////////////////////////////////////////////////////


    // Debug Log
    $config->debug_conversion ? App::Log (CONVERSION_LOG, "\nPreparing to create video thumbnail...") : null;

    ### Create video thumbnail image
    $thumb_command = "$ffmpeg_path -i " . escapeshellarg($raw_video) . " -ss $thumb_position " . Settings::Get('thumb_options') . " " . escapeshellarg($thumb) . " >> " . escapeshellarg($debug_log) . " 2>&1";
    Plugin::Trigger ('encode.before_create_thumbnail');

    // Debug Log
    $log_msg = "\n\n\n\n==================================================================\n";
    $log_msg .= "THUMB CREATION\n";
    $log_msg .= "==================================================================\n\n";
    $log_msg .= "Thumb Creation Command: $thumb_command\n\n";
    $log_msg .= "Thumb Creation Output:\n\n";
    $config->debug_conversion ? App::Log (CONVERSION_LOG, "Thumbnail Creation Command: " . $thumb_command) : null;
    App::Log ($debug_log, $log_msg);

    exec ($thumb_command);    // Execute Thumb Creation Command
    Plugin::Trigger ('encode.create_thumbnail');









    /////////////////////////////////////////////////////////////
    //                        STEP 7                           //
    //               Update Video Information                  //
    /////////////////////////////////////////////////////////////


    // Debug Log
    $config->debug_conversion ? App::Log (CONVERSION_LOG, "\nUpdating video information...") : null;

    // Update database with new video status information
    $data['duration'] = $duration[0];
    Plugin::Trigger ('encode.before_update');
    $video->Update ($data);
    Plugin::Trigger ('encode.update');

    // Activate video
    $video->Approve ('activate');







    /////////////////////////////////////////////////////////////
    //                         STEP 8                          //
    //                        Overlay                          //
    /////////////////////////////////////////////////////////////

    /*
    # Add overlay : http://stackoverflow.com/a/10920872
    $overlay = dirname(__FILE__) . "/watermark/watermark.png";
    $overlay_command = $ffmpeg_path . ' -y -i ' .  escapeshellarg($flv) . ' -i ' . $overlay . ' -b:v 64k -filter_complex "overlay=main_w/2-overlay_w/2:main_h/2-overlay_h/2" -codec:a copy ' . escapeshellarg($flv) . ' >> ' . escapeshellarg($debug_log) . ' 2>&1';

    // Debug Log
    $log_msg = "\n\n\n\n==================================================================\n";
    $log_msg .= "Overlay CREATION\n";
    $log_msg .= "==================================================================\n\n";
    $log_msg .= "Overlay Creation Command: $overlay_command\n\n";
    $log_msg .= "Overlay Creation Output:\n\n";
    $config->debug_conversion ? App::Log (CONVERSION_LOG, "Overlay Creation Command: " . $overlay_command) : null;
    App::Log ($debug_log, $log_msg);

    exec ($overlay_command);

    // Debug Log
    $config->debug_conversion ? App::Log (CONVERSION_LOG, 'Overlay was applied on video...') : null;
     */







    /////////////////////////////////////////////////////////////
    //                         STEP 9                          //
    //                       vtt sprite                        //
    /////////////////////////////////////////////////////////////

    # https://github.com/vlanard/videoscripts
    $sprite_command = '/usr/local/bin/python2.7 ' . DOC_ROOT . '/cc-content/plugins/CumulusCustomEncoding/makesprites.py ' .  escapeshellarg($raw_video) . ' >> ' . escapeshellarg($debug_log) . ' 2>&1';


    // Debug Log
    $log_msg = "\n\n\n\n==================================================================\n";
    $log_msg .= "Sprite CREATION\n";
    $log_msg .= "==================================================================\n\n";
    $log_msg .= "Sprite Creation Command: $sprite_command\n\n";
    $log_msg .= "Sprite Creation Output:\n\n";
    $config->debug_conversion ? App::Log (CONVERSION_LOG, "Sprite Creation Command: " . $sprite_command) : null;
    App::Log ($debug_log, $log_msg);

    exec ($sprite_command);

    // Debug Log
    $config->debug_conversion ? App::Log (CONVERSION_LOG, 'Sprite generated for this video...') : null;







    /////////////////////////////////////////////////////////////
    //                         STEP 10                         //
    //                        Clean up                         //
    /////////////////////////////////////////////////////////////

    try {

        // Debug Log
        $config->debug_conversion ? App::Log (CONVERSION_LOG, 'Deleting raw video...') : null;

        ### Delete raw videos & pre-faststart files
        unlink($raw_video);
        unlink($mobile_temp);

        ### Delete encoding log files
        if ($config->debug_conversion) {
            App::Log (CONVERSION_LOG, "Video ID: $video->video_id, has completed processing!\n");
        }

    } catch (Exception $e) {
        App::Alert ('Error During Video Encoding', $e->getMessage());
        App::Log (CONVERSION_LOG, $e->getMessage());
    }

    Plugin::Trigger ('encode.complete');


} catch (Exception $e) {
    App::Alert ('Error During Video Encoding', $e->getMessage());
    App::Log (CONVERSION_LOG, $e->getMessage());
    exit();
}

?>
