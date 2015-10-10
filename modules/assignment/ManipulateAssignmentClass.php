<?php
/**
 * Created by IntelliJ IDEA.
 * User: Sam
 * Date: 10/9/15
 * Time: 22:46
 */

class ManipulateAssignmentClass {

    var $assignment;

    function __construct(){
        // Do nothing
    }

    /*
     *
     * Part I: Load Assignment
     * This part is used to load assignment for stream view or for class
     *
     * studentLoadAssignment($student) loads assignments for stream view,
     * where $student is the uid of the student
     *
     * classLoadAssignment($class) loads assignments for a class.
     * It can be both called while loading for students and for teachers
     * $class is the class id of the class requiring to load assignment
     *
     */
    function studentLoadAssignment($student){
        global $conn;

        $sql = "SELECT * from student WHERE id='$student'";
        $result = $conn->query($sql);

        $sqlForClass = "";

        while($row = $result->fetch_assoc()) {
            $classIDs = explode(";",$row['class']);

            if (count($classIDs)>1){
                $sqlForClass = "class = ".$classIDs[1]." ";
                for ($i = 2; $i < count($classIDs) ; $i++){
                    $classID = $classIDs[$i];
                    $sqlForClass = $sqlForClass."OR class = ".$classID." ";
                }
            }
        }

        $sqlForClass = "(".$sqlForClass.")";

        if ($sqlForClass == "()"){
            $sqlForClass = "1 = 0";
        }

        $sql = "SELECT * from assignment WHERE ( $sqlForClass AND dueday > curdate() ) OR class = '39' ORDER BY dueday ASC";
        $result = $conn->query($sql);

        $arr = array();
        $counter = 0;

        // Exclude the finished assignments
        while($row = $result->fetch_assoc()) {
            $id = $row['id'];
            $class = $row['class'];

            $finished = false;

            $sql2 = "SELECT * FROM personalassignment WHERE assignment = $id AND uid = $student";
            $result2 = $conn->query($sql2);
            if ($result2->num_rows > 0) {
                $finished = true;
            }

            if ($finished == true){
                // Do nothing
            }else{
                $unitAssignment = new UnitAssignment();
                $unitAssignment->constructFromDBRow($row, $class, $finished);
                $arr[$counter] = $unitAssignment;
                $counter++;
            }

        }

        return json_encode($arr);
    }

    function classLoadAssignment($class){
        global $conn;

        $sql = "SELECT * FROM assignment WHERE class = '$class' AND dueday > (curdate() - 180) ORDER BY dueday DESC";
        $result = $conn->query($sql);

        $arr = array();
        $counter = 0;

        while($row = $result->fetch_assoc()) {

            $unitAssignment = new UnitAssignment();
            $unitAssignment->constructFromDBRow($row, $class, false);
            $arr[$counter] = $unitAssignment;
            $counter++;
        }

        return json_encode($arr);
    }
    // Part I ends



    /*
     *
     * Part II: Deal with a specific assignment
     * This part is used to process a specific assignment
     *
     * setAssignment($assignment) sets up the common variable $assignment for in-class calling
     * All part II operation must be preceded by this function
     *
     * updateAssignment($content, $teacher) updates an assignment based on its new content already assigned by a teacher,
     * where $content is the new content, ans $teacher is the uid of the teacher
     *
     * deleteAssignment($teacher) deletes an assignment assigned by a teacher,
     * where $teacher is the uid of the teacher
     *
     * markCompletion($actual, $student) marks an assignment as completed,
     * where $actual is the time used (can be anything, which would be automatically processed by the function),
     * and $student is the uid of a student
     *
     * markUnCompletion($student) marks an assignment as uncompleted,
     * where $student is the uid of a student
     * (The function is NOT called by the client side, but do not delete!)
     *
     */
    function setAssignment($assignment){
        $this->assignment = $assignment;
    }

    function updateAssignment($content, $teacher){
        global $conn;

        $sql = "UPDATE assignment SET content = '$content' WHERE id = '$this->assignment' AND teacher = '$teacher'";

        if ($conn->query($sql) === TRUE) {
            echo "Success!";
        } else {
            echo "Unexpected error.";
        }
    }

    function deleteAssignment($teacher){
        global $conn;

        $sql = "DELETE FROM assignment WHERE id = '$this->assignment' AND teacher = '$teacher'";
        $sql2 = "DELETE FROM personalassignment WHERE assignment = '$this->assignment'";

        if ($conn->query($sql) === TRUE && $conn->query($sql2) === TRUE ) {
            echo "Successfully deleted one assignment.";
        } else {
            echo "Unexpected Error";
        }
    }

    function markCompletion($actual, $student){
        global $conn;

        if (is_numeric($actual)){
            $actual = floatval($actual);
            if ($actual > 0){
                // DO nothing.
            }else{
                $actual = 0;
            }
        }else{
            $actual = 0;
        }

        $sql = "INSERT INTO personalassignment (assignment, uid, actual) VALUES ($this->assignment, $student, $actual)";

        if ($conn->query($sql) === TRUE) {
            echo "Thank you for cooperation!";
        } else {
            echo "Unexpected error.";
        }
    }

    function markUnCompletion($student){
        global $conn;

        $sql = "DELETE FROM personalassignment WHERE assignment = '$this->assignment' AND uid = $student";

        if ($conn->query($sql) === TRUE) {
            echo "Success!";
        } else {
            echo "Unexpected error.";
        }
    }
    // Part II ends


    /*
     *
     * Part III: Add Assignment
     * This part is used to add a new assignment
     *
     */
    function addAssignment(){
        global $conn;

        $result = checkForceQuit();

        $teacher = $result->uid;


        $type = $_POST['type'];
        $dueday = "null";
        $duration = 0.0;
        $attachment = "null";
        if ($type == "1"){
            $duration = $_POST['duration'];
        }
        $dueday = $_POST['dueday'];
        if ($dueday == ""){
            $dueday = "2038-1-1";
        }
        $dueday = date("Y-m-d", strtotime($dueday));

        if ($_POST['hasAttachment'] == "true"){
            function genRandomString(){
                $length = 5;
                $characters = "0123456789ABCDEFGHIJKLMNOPQRSTUVWZYZ";

                $real_string_length = strlen($characters) ;
                $string="id";

                for ($p = 0; $p < $length; $p++)
                {
                    $string .= $characters[mt_rand(0, $real_string_length-1)];
                }

                return strtolower($string);
            }

            $target_dir = "/files/attachments/";

            $attachment = "";

            for ($i = 0; $i < count($_FILES["attachment"]['name']); $i++ ){
                $originalName = $_FILES["attachment"]['name'][$i];
                $realNameArr = explode(".",$originalName);
                $realName = $realNameArr[0];
                $rand = genRandomString();
                $fileType = pathinfo($originalName, PATHINFO_EXTENSION);
                $final_filename = $realName."_".time().".".$fileType;
                $target_file = $_SERVER['DOCUMENT_ROOT'].$target_dir .$final_filename;

                $attachment .= ";".$target_dir.$final_filename.";".$originalName;

                move_uploaded_file($_FILES["attachment"]["tmp_name"][$i], $target_file);
            }


        }
        $content = $_POST['content'];
        $class = $_POST['class'];

        $sql2 = "INSERT INTO assignment (type, content, attachment, publish, dueday, duration, class, teacher) VALUES ($type, '$content', '$attachment', now(), '$dueday', $duration, '$class', '$teacher')";
        $conn->query($sql2);


        $sql3 = "SELECT * from student WHERE class LIKE '%;$class;%' OR class LIKE '%;$class' ORDER BY id ASC";
        $result = $conn->query($sql3);


        while($row = $result->fetch_assoc()) {
            $id = $row['id'];
            $device = new Device($id);
            $device->push('The teacher has just assigned homework.');
        }

        echo "Success";
    }
    // Part III ends


}