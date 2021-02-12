<?php

require_once(dirname(__FILE__). '/../../config.php');
require_once($CFG->dirroot.'/mod/arete/locallib.php');
require_once($CFG->dirroot.'/mod/arete/classes/filemanager.php');
require_once($CFG->dirroot.'/mod/arete/classes/output/outputs.php');

//current user
global $USER;

//Get module id, course and moudle infos
$id = required_param('id', PARAM_INT); // Course Module ID.
$urlparams = array('id' => $id, 'name' => $name);
$url = new moodle_url('/mod/arete/view.php', $urlparams);
list ($course, $cm) = get_course_and_cm_from_cmid($id, 'arete');

//page configuration
$PAGE->set_url($url);
$PAGE->set_title(get_string('modulename', 'arete'));
$PAGE->requires->css('/mod/arete/css/style.css');  //pagination css file

//need to be login for this course
require_course_login($course, false, $cm);

//print Moodle header
echo $OUTPUT->header();

//id of this activity
$moduleid = $cm->instance;

//course context
$context = context_course::instance($course->id);

//get all ARLEMS from DB
$arlems_list = getAllArlems(); 


// if all arlem files is deleted, delete the activity too and redirect to the course page
if(count($arlems_list) == 0)
{
    //if no ARLEM files is exist in the DB delete the activity too
    arete_delete_activity($moduleid);
    
    //return to the course page
    redirect($CFG->wwwroot . '/course/view.php?id='. $PAGE->course->id, array());
    return;
}


//every body view
if(has_capability('mod/arete:view', $context)){
    
    //Print the description
    echo '<span class="titles">' . get_string('description', 'arete') . '</span>';
    $description = $DB->get_field('arete', 'intro', array('id' => $moduleid));
    echo '<h5>'.$description.'</h5></br>';

}


///////Students view and teacher view
if(has_capability('mod/arete:assignedarlemfile', $context) || has_capability('mod/arete:arlemfulllist', $context))
{
   //add the role to the top of the advtivity
   $roleassignments = $DB->get_record('role_assignments', array('userid' => $USER->id)); 
   if(isset($roleassignments->roleid)){
          $role = $DB->get_record('role', array('id' => $roleassignments->roleid)); 
          echo '<div class="right">'. get_string('rolelabel', 'arete') . '<span id="role">' .$role->shortname . '</span></div>';
   }else{
           echo '<div class="right">'. get_string('roleundefined', 'arete') . '</div>';
   }


    //label that show this arlem is assinged to this activity
   echo '<span class="titles">' . get_string('assignedarlem', 'arete') . '</span>';
    
   //Get the assigned ARLEM from DB
   $activity_arlem = $DB->get_record('arete_arlem', array('areteid' => $moduleid));
   
   //Get the ARLEM id of the assigned from DB
   $arleminfo = $DB->get_record('arete_allarlems', array('fileid' => $activity_arlem->arlemid));
   
   //print the assigned ARLEM in a single table row if it is exist
   if($arleminfo != null){
        $fileinfo = $DB->get_record('files', array ('id' => $activity_arlem->arlemid, 'itemid' => $arleminfo->itemid));
        $arlemfile = getArlemByName($fileinfo->filename,  $fileinfo->itemid);
        echo html_writer::table(draw_arlem_table(array($arlemfile))); //student arlem
   }else{
       
       //print a notification if no ARLEM is assigned yet to this activity
       echo $OUTPUT->notification(get_string('notassignedyer' , 'arete'));
   }

}


///////////Teachers View
if(has_capability('mod/arete:arlemfulllist', $context))
{
    //maximum item on each page
    $max_number_on_page = 10; 
    
    //get the active page id from GET
    $page_number = filter_input(INPUT_GET, 'pnum');//current page number
   
    //start at first page if pnum is not exist in the page url
    if(!isset($page_number))
    {
        $page_number = 1;
    }
    
    // split ARLEMs list to small lists
    $splitet_list = array_chunk($arlems_list, $max_number_on_page); 


    //label that show the list of all arlems which  are available
    echo '<br><span class="titles">' . get_string('availabledarlem', 'arete') . '</span>';
   
    echo '<form action="classes/save_assignment.php" method="post">';
    echo html_writer::table(draw_arlem_table($splitet_list[$page_number-1],  true)); //arlems table
    echo '<input type="hidden" id="returnurl" name="returnurl" value="'. $CFG->wwwroot .'/mod/arete/view.php?id='. $id . '&pnum=' . $page_number . '">';
    echo '<input type="hidden" id="moduleid" name="moduleid" value="'. $moduleid .'">';
    echo '<div class="right"><input class="btn btn-primary" type="button" value="Save" onClick="confirmSubmit(this.form);"></div>'; //submit button
    echo '</form>';
    

    // pagination 
    $pagination = new pagination();
    echo $pagination->getPagination($splitet_list, $page_number, $id);

    
    //check and set the radio button of the assigend arlem on loading the page
    update_assignment($moduleid);
    
//// for testing only (REMOVE on release)
////  Delete all test arlems 
//    $arlemsList = getAllArlems( true);
//    foreach ($arlemsList as $arlem) {
//           deletePluginArlem($arlem->get_filename(), $arlem->get_itemid());
//    }
//    $arlemsList = getAllUserArlems( true, 2 ,true);
//    foreach ($arlemsList as $arlem) {
//        deleteUserArlem($arlem->get_filename(), $arlem->get_itemid(), 2);
//    }
////        
        
}


//confirm before submit
echo '<script  type="text/javascript">
function confirmSubmit(form)
{
	var checked = document.querySelectorAll(\'input#deleteCheckbox:checked\');

	if (checked.length === 0) {

		form.submit();
	} else {

    if (confirm("Are you sure you want to delete these files?")) {
         form.submit();
		}
	}
}
</script>';
echo $OUTPUT->footer();


