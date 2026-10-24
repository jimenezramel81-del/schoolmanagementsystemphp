<?php


$modules = [

    // ---------------------------------------------------------------- Administration
    'usertype' => [
        'table' => 'tbl_usertype', 'label' => 'User Types', 'singular' => 'User Type', 'id_field' => 'ID',
        'search_fields' => ['USERTYPE'],
        'dup_message' => 'That user type already exists.',
        'fields' => [
            ['name' => 'USERTYPE', 'label' => 'User Type', 'type' => 'text', 'maxlength' => 50],
        ],
    ],

    'users' => [
        'table' => 'tbl_users', 'label' => 'Users', 'singular' => 'User', 'id_field' => 'ID',
        'search_fields' => ['USERNAME', 'FULLNAME', '@USERTYPE_ID'],
        'filters' => ['USERTYPE_ID'],
        'title' => ['USERNAME'],
        'protect_self' => true,
        'dup_message' => 'That username is already taken.',
        'fields' => [
            ['name' => 'USERNAME', 'label' => 'Username', 'type' => 'text', 'maxlength' => 50],
            ['name' => 'FULLNAME', 'label' => 'Full Name', 'type' => 'text', 'maxlength' => 100],
            ['name' => 'PASSWORD', 'label' => 'Password', 'type' => 'password'],
            ['name' => 'USERTYPE_ID', 'label' => 'User Type', 'type' => 'select',
                'ref_table' => 'tbl_usertype', 'ref_id' => 'ID', 'ref_label' => 'USERTYPE', 'filter_label' => 'All user types'],
            ['name' => 'CREATED_AT', 'label' => 'Created', 'type' => 'datetime', 'form' => false],
        ],
    ],

    // ---------------------------------------------------------------- Academics
    'course' => [
        'table' => 'tbl_course', 'label' => 'Courses', 'singular' => 'Course', 'id_field' => 'ID',
        'search_fields' => ['COURSE', 'DESCRIPTION'],
        'title' => ['COURSE'],
        'fields' => [
            ['name' => 'COURSE', 'label' => 'Course Code', 'type' => 'text', 'maxlength' => 50, 'placeholder' => 'e.g. BSIT'],
            ['name' => 'DESCRIPTION', 'label' => 'Description', 'type' => 'text', 'maxlength' => 255, 'full' => true,
                'placeholder' => 'e.g. Bachelor of Science in Information Technology'],
        ],
    ],

    'yearlevel' => [
        'table' => 'tbl_yearlevel', 'label' => 'Year Levels', 'singular' => 'Year Level', 'id_field' => 'ID',
        'search_fields' => ['YEARLEVEL'],
        'fields' => [
            ['name' => 'YEARLEVEL', 'label' => 'Year Level', 'type' => 'text', 'maxlength' => 50, 'placeholder' => 'e.g. 1st Year'],
        ],
    ],

    'term' => [
        'table' => 'tbl_term', 'label' => 'Terms', 'singular' => 'Term', 'id_field' => 'ID',
        'search_fields' => ['TERM'],
        'fields' => [
            ['name' => 'TERM', 'label' => 'Term', 'type' => 'text', 'maxlength' => 50, 'placeholder' => 'e.g. 1st Semester'],
        ],
    ],

    'schoolyear' => [
        'table' => 'tbl_schoolyear', 'label' => 'School Years', 'singular' => 'School Year', 'id_field' => 'ID',
        'search_fields' => ['SCHOOLYEAR'],
        'fields' => [
            ['name' => 'SCHOOLYEAR', 'label' => 'School Year', 'type' => 'text', 'maxlength' => 20, 'placeholder' => 'e.g. 2026-2027'],
        ],
    ],

    'section' => [
        'table' => 'tbl_section', 'label' => 'Sections', 'singular' => 'Section', 'id_field' => 'ID',
        'search_fields' => ['SECTION', '@COURSE_ID'],
        'filters' => ['COURSE_ID', 'YEARLEVEL_ID'],
        'title' => ['SECTION'],
        'fields' => [
            ['name' => 'SECTION', 'label' => 'Section', 'type' => 'text', 'maxlength' => 50, 'placeholder' => 'e.g. BSIT-1A'],
            ['name' => 'COURSE_ID', 'label' => 'Course', 'type' => 'select',
                'ref_table' => 'tbl_course', 'ref_id' => 'ID', 'ref_label' => 'COURSE', 'filter_label' => 'All courses'],
            ['name' => 'YEARLEVEL_ID', 'label' => 'Year Level', 'type' => 'select', 'required' => false,
                'ref_table' => 'tbl_yearlevel', 'ref_id' => 'ID', 'ref_label' => 'YEARLEVEL', 'filter_label' => 'All year levels'],
        ],
    ],

    'subjects' => [
        'table' => 'tbl_subjects', 'label' => 'Subjects', 'singular' => 'Subject', 'id_field' => 'ID',
        'search_fields' => ['SUBJECTCODE', 'SUBJECTNAME', '@COURSE_ID'],
        'filters' => ['COURSE_ID', 'YEARLEVEL_ID', 'TERM_ID'],
        'title' => ['SUBJECTCODE', 'SUBJECTNAME'],
        'fields' => [
            ['name' => 'SUBJECTCODE', 'label' => 'Subject Code', 'type' => 'text', 'maxlength' => 20, 'placeholder' => 'e.g. IT101'],
            ['name' => 'SUBJECTNAME', 'label' => 'Subject Name', 'type' => 'text', 'maxlength' => 100],
            ['name' => 'UNITS', 'label' => 'Units', 'type' => 'number', 'min' => 1, 'max' => 12],
            ['name' => 'COURSE_ID', 'label' => 'Course', 'type' => 'select',
                'ref_table' => 'tbl_course', 'ref_id' => 'ID', 'ref_label' => 'COURSE', 'filter_label' => 'All courses'],
            ['name' => 'YEARLEVEL_ID', 'label' => 'Year Level', 'type' => 'select', 'required' => false,
                'ref_table' => 'tbl_yearlevel', 'ref_id' => 'ID', 'ref_label' => 'YEARLEVEL', 'filter_label' => 'All year levels'],
            ['name' => 'TERM_ID', 'label' => 'Term', 'type' => 'select', 'required' => false,
                'ref_table' => 'tbl_term', 'ref_id' => 'ID', 'ref_label' => 'TERM', 'filter_label' => 'All terms'],
        ],
    ],

    // ---------------------------------------------------------------- People
    'students' => [
        'table' => 'tbl_students', 'label' => 'Students', 'singular' => 'Student', 'id_field' => 'ID',
        'search_fields' => ['STUDENT_NO', 'FIRSTNAME', 'MIDDLENAME', 'LASTNAME', 'EMAIL',
            "=CONCAT_WS(' ', m.FIRSTNAME, m.LASTNAME)", "=CONCAT_WS(' ', m.LASTNAME, m.FIRSTNAME)", '@COURSE_ID', '@SECTION_ID'],
        'filters' => ['COURSE_ID', 'YEARLEVEL_ID', 'SECTION_ID', 'STATUS'],
        'list_order' => ['PHOTO', 'STUDENT_NO', 'LASTNAME', 'FIRSTNAME', 'COURSE_ID', 'YEARLEVEL_ID', 'SECTION_ID', 'STATUS'],
        'title' => ['FIRSTNAME', 'LASTNAME'],
        'dup_message' => 'A student with that Student No. already exists.',
        'row_links' => [
            ['label' => 'View', 'icon' => 'eye', 'url' => 'student_view.php?id={id}', 'perm' => 'students', 'primary' => true],
        ],
        'fields' => [
            ['name' => 'STUDENT_NO', 'label' => 'Student No.', 'type' => 'text', 'maxlength' => 20, 'placeholder' => 'e.g. 2026-0001', 'section' => 'Personal Information'],
            ['name' => 'PHOTO', 'label' => 'Photo (JPG / PNG, max 2 MB)', 'type' => 'image', 'required' => false, 'upload_dir' => 'uploads/students/'],
            ['name' => 'FIRSTNAME', 'label' => 'First Name', 'type' => 'text', 'maxlength' => 50],
            ['name' => 'MIDDLENAME', 'label' => 'Middle Name', 'type' => 'text', 'maxlength' => 50, 'required' => false, 'list' => false],
            ['name' => 'LASTNAME', 'label' => 'Last Name', 'type' => 'text', 'maxlength' => 50],
            ['name' => 'GENDER', 'label' => 'Gender', 'type' => 'enum', 'options' => ['Male', 'Female'], 'list' => false],
            ['name' => 'BIRTHDATE', 'label' => 'Date of Birth', 'type' => 'date', 'required' => false, 'list' => false],

            ['name' => 'EMAIL', 'label' => 'Email', 'type' => 'email', 'maxlength' => 100, 'required' => false, 'list' => false, 'section' => 'Contact Details'],
            ['name' => 'CONTACT', 'label' => 'Contact No.', 'type' => 'tel', 'maxlength' => 30, 'required' => false, 'list' => false],
            ['name' => 'ADDRESS', 'label' => 'Home Address', 'type' => 'textarea', 'maxlength' => 255, 'required' => false, 'list' => false, 'full' => true],

            ['name' => 'GUARDIAN_NAME', 'label' => 'Parent / Guardian', 'type' => 'text', 'maxlength' => 100, 'required' => false, 'list' => false, 'section' => 'Parent / Guardian'],
            ['name' => 'GUARDIAN_CONTACT', 'label' => 'Guardian Contact No.', 'type' => 'tel', 'maxlength' => 30, 'required' => false, 'list' => false],

            ['name' => 'COURSE_ID', 'label' => 'Course', 'type' => 'select', 'section' => 'Academic Information',
                'ref_table' => 'tbl_course', 'ref_id' => 'ID', 'ref_label' => 'COURSE', 'filter_label' => 'All courses'],
            ['name' => 'YEARLEVEL_ID', 'label' => 'Year Level', 'type' => 'select',
                'ref_table' => 'tbl_yearlevel', 'ref_id' => 'ID', 'ref_label' => 'YEARLEVEL', 'filter_label' => 'All year levels'],
            ['name' => 'SECTION_ID', 'label' => 'Section', 'type' => 'select', 'required' => false,
                'ref_table' => 'tbl_section', 'ref_id' => 'ID', 'ref_label' => 'SECTION', 'filter_label' => 'All sections'],
            ['name' => 'STATUS', 'label' => 'Status', 'type' => 'enum', 'badge' => true, 'filter_label' => 'All statuses',
                'options' => ['Active', 'Inactive', 'Graduated', 'Dropped']],
            ['name' => 'USER_ID', 'label' => 'Login Account (optional)', 'type' => 'select', 'required' => false, 'list' => false, 'full' => true,
                'ref_sql' => "SELECT u.ID AS id, CONCAT(u.USERNAME, ' - ', u.FULLNAME) AS label FROM tbl_users u JOIN tbl_usertype t ON t.ID = u.USERTYPE_ID WHERE t.USERTYPE = 'Student'"],
        ],
    ],

    'instructors' => [
        'table' => 'tbl_instructors', 'label' => 'Instructors', 'singular' => 'Instructor', 'id_field' => 'ID',
        'search_fields' => ['FIRSTNAME', 'MIDDLENAME', 'LASTNAME', 'EMAIL',
            "=CONCAT_WS(' ', m.FIRSTNAME, m.LASTNAME)"],
        'list_order' => ['PHOTO', 'SALUTATION', 'FIRSTNAME', 'MIDDLENAME', 'LASTNAME', 'EMAIL', 'CONTACT'],
        'title' => ['SALUTATION', 'FIRSTNAME', 'LASTNAME'],
        'fields' => [
            ['name' => 'SALUTATION', 'label' => 'Salutation', 'type' => 'enum', 'options' => ['Mr.', 'Ms.', 'Mrs.', 'Dr.', 'Engr.', 'Prof.']],
            ['name' => 'PHOTO', 'label' => 'Photo (JPG / PNG, max 2 MB)', 'type' => 'image', 'required' => false, 'upload_dir' => 'uploads/instructors/'],
            ['name' => 'FIRSTNAME', 'label' => 'First Name', 'type' => 'text', 'maxlength' => 50],
            ['name' => 'MIDDLENAME', 'label' => 'Middle Name', 'type' => 'text', 'maxlength' => 50, 'required' => false],
            ['name' => 'LASTNAME', 'label' => 'Last Name', 'type' => 'text', 'maxlength' => 50],
            ['name' => 'EMAIL', 'label' => 'Email', 'type' => 'email', 'maxlength' => 100, 'required' => false],
            ['name' => 'CONTACT', 'label' => 'Contact No.', 'type' => 'tel', 'maxlength' => 30, 'required' => false],
            ['name' => 'USER_ID', 'label' => 'Login Account (optional)', 'type' => 'select', 'required' => false, 'list' => false, 'full' => true,
                'ref_sql' => "SELECT u.ID AS id, CONCAT(u.USERNAME, ' - ', u.FULLNAME) AS label FROM tbl_users u JOIN tbl_usertype t ON t.ID = u.USERTYPE_ID WHERE t.USERTYPE = 'Instructor'"],
        ],
    ],

    // ---------------------------------------------------------------- Classes
    'schedules' => [
        'table' => 'tbl_schedules', 'label' => 'Class Schedules', 'singular' => 'Class Schedule', 'id_field' => 'ID',
        'search_fields' => ['@SUBJECT_ID', '@SECTION_ID', '@INSTRUCTOR_ID', 'ROOM'],
        'filters' => ['SCHOOLYEAR_ID', 'TERM_ID', 'SECTION_ID', 'INSTRUCTOR_ID'],
        'list_order' => ['SUBJECT_ID', 'SECTION_ID', 'INSTRUCTOR_ID', 'DAYS', 'TIME_START', 'TIME_END', 'ROOM', 'SCHOOLYEAR_ID', 'TERM_ID'],
        'title' => ['@SUBJECT_ID', '@SECTION_ID'],
        'validate' => 'validate_schedule',
        'row_links' => [
            ['label' => 'Grades',      'icon' => 'award',        'url' => 'grades.php?schedule={id}',     'perm' => 'grades'],
            ['label' => 'Attendance',  'icon' => 'check-square', 'url' => 'attendance.php?schedule={id}', 'perm' => 'attendance'],
            ['label' => 'Enroll section', 'icon' => 'user-plus', 'url' => 'bulk_enroll.php?schedule={id}', 'perm' => 'enrollments', 'write' => true],
        ],
        'fields' => [
            ['name' => 'SUBJECT_ID', 'label' => 'Subject', 'type' => 'select', 'full' => true, 'filter_label' => 'All subjects',
                'ref_sql' => "SELECT ID AS id, CONCAT(SUBJECTCODE, ' - ', SUBJECTNAME) AS label FROM tbl_subjects"],
            ['name' => 'SECTION_ID', 'label' => 'Section', 'type' => 'select', 'filter_label' => 'All sections',
                'ref_table' => 'tbl_section', 'ref_id' => 'ID', 'ref_label' => 'SECTION'],
            ['name' => 'INSTRUCTOR_ID', 'label' => 'Instructor', 'type' => 'select', 'filter_label' => 'All instructors',
                'ref_sql' => "SELECT ID AS id, CONCAT_WS(' ', SALUTATION, FIRSTNAME, LASTNAME) AS label FROM tbl_instructors"],
            ['name' => 'DAYS', 'label' => 'Days', 'type' => 'enum',
                'options' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Mon/Wed', 'Tue/Thu', 'Mon/Wed/Fri', 'Tue/Thu/Sat']],
            ['name' => 'ROOM', 'label' => 'Room', 'type' => 'text', 'maxlength' => 50, 'required' => false, 'placeholder' => 'e.g. Room 201'],
            ['name' => 'TIME_START', 'label' => 'Start Time', 'type' => 'time'],
            ['name' => 'TIME_END', 'label' => 'End Time', 'type' => 'time'],
            ['name' => 'SCHOOLYEAR_ID', 'label' => 'School Year', 'type' => 'select', 'filter_label' => 'All school years',
                'ref_table' => 'tbl_schoolyear', 'ref_id' => 'ID', 'ref_label' => 'SCHOOLYEAR'],
            ['name' => 'TERM_ID', 'label' => 'Term', 'type' => 'select', 'filter_label' => 'All terms',
                'ref_table' => 'tbl_term', 'ref_id' => 'ID', 'ref_label' => 'TERM'],
        ],
    ],

    'enrollments' => [
        'table' => 'tbl_enrollments', 'label' => 'Enrollments', 'singular' => 'Enrollment', 'id_field' => 'ID',
        'search_fields' => ['@STUDENT_ID', '@SCHEDULE_ID'],
        'filters' => ['SCHEDULE_ID'],
        'title' => ['@STUDENT_ID'],
        'dup_message' => 'This student is already enrolled in that class.',
        'fields' => [
            ['name' => 'STUDENT_ID', 'label' => 'Student', 'type' => 'select', 'full' => true,
                'ref_sql' => "SELECT ID AS id, CONCAT(STUDENT_NO, ' - ', LASTNAME, ', ', FIRSTNAME) AS label FROM tbl_students",
                'options_sql' => "SELECT ID AS id, CONCAT(STUDENT_NO, ' - ', LASTNAME, ', ', FIRSTNAME) AS label FROM tbl_students WHERE STATUS = 'Active'"],
            ['name' => 'SCHEDULE_ID', 'label' => 'Class', 'type' => 'select', 'full' => true, 'filter_label' => 'All classes',
                'ref_sql' => "SELECT sc.ID AS id, CONCAT(sub.SUBJECTCODE, ' | ', sec.SECTION, ' | ', sc.DAYS, ' ', DATE_FORMAT(sc.TIME_START, '%l:%i %p'), ' | ', sy.SCHOOLYEAR, ' ', t.TERM) AS label
                              FROM tbl_schedules sc
                              JOIN tbl_subjects sub ON sub.ID = sc.SUBJECT_ID
                              JOIN tbl_section sec ON sec.ID = sc.SECTION_ID
                              JOIN tbl_schoolyear sy ON sy.ID = sc.SCHOOLYEAR_ID
                              JOIN tbl_term t ON t.ID = sc.TERM_ID"],
            ['name' => 'ENROLLED_AT', 'label' => 'Enrolled On', 'type' => 'datetime', 'form' => false],
        ],
    ],

];
