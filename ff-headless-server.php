<?php
/*
Plugin Name: Fluent Forms Headless Server
Plugin URI:  https://wordpress.org/plugins/fluentform
Description: Divi Module For Fluent Forms
Version:     1.0.0
Author:      WPManageNinja LLC
Author URI:  https://wpmanageninja.com
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: ff_headless
Domain Path: /languages
*/

/*
 * https:/domain.com/?ff_capture=1&form_id=1
 * To send request make sure you have send 'form_body' in the request body as an array/json of the form data
 */

class FfHeadlessServer
{
    public function __construct()
    {
        $this->registerServerEndpoint();
    }

    public function registerServerEndpoint()
    {
        if (!isset($_REQUEST['ff_capture'])) {
            return;
        }

        /*
         * You may verify the request here to validate the data
         */
        $formId = intval($_REQUEST['form_id']);


        $acceptedFormIds = [1,2,3]; // define your accepted form ids

        if(in_array($formId, $acceptedFormIds)) {
            wp_send_json([
                'error' => 'This form id does not support remote submissions'
            ], 423);
        }


        $form = wpFluent()->table('fluentform_forms')
            ->find($formId);

        if (!$form) {
            wp_send_json([
                'error' => 'No Form Found'
            ], 423);
        }


        // We should verify the request
        // Maybe you can send a hashed key and verify that here.

        $formData = $_REQUEST['form_body'];

        if(!is_array($formData)) {
            $formData = json_decode($formData, true);
            if (json_last_error() == JSON_ERROR_NONE) {
                wp_send_json([
                    'error' => 'Form Data is not valid'
                ], 423);
            }
        }

        $formData = wp_unslash($formData);

        // Here 'user_session_id' is the unique key that we want to match same user and same form
        $prevSubmission = $this->maybeSameSubmission($formData, $form, 'user_session_id');

        if ($prevSubmission) {
            $submissionId = $this->recordPrevSubmission($prevSubmission, $formData, $form);
        } else {
            $submissionId = $this->recordNewEntry($formData, $form);
        }

        wp_send_json_success([
            'insert_id' => $submissionId
        ]);

    }

    private function recordNewEntry($formData, $form)
    {
        $formHandler = new \FluentForm\App\Modules\Form\FormHandler(wpFluentForm());
        $previousItem = wpFluent()->table('fluentform_submissions')
            ->where('form_id', $form->id)
            ->orderBy('id', 'DESC')
            ->first();

        $serialNumber = 1;

        if ($previousItem) {
            $serialNumber = $previousItem->serial_number + 1;
        }

        $insertId = wpFluent()
            ->table('fluentform_submissions')
            ->insert([
                'form_id'       => $form->id,
                'response'      => json_encode($formData),
                'source_url'    => isset($_REQUEST['src']) ? $_REQUEST['src'] : '',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
                'serial_number' => $serialNumber
            ]);

        // This is required to fire the form events.
        $formHandler->processFormSubmissionData($insertId, $formData, $form);

        return $insertId;
    }

    private function recordPrevSubmission($prevSubmission, $formData, $form)
    {
        $prevData = json_decode($prevSubmission->response, true);
        $data = array_merge($prevData, $formData);
        wpFluent()
            ->table('fluentform_submissions')
            ->where('id', $prevSubmission->id)
            ->update([
                'response'   => json_encode($data),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

        wpFluent()->table('fluentform_entry_details')
            ->where('submission_id', $prevSubmission->id)
            ->delete();

        $entries = new \FluentForm\App\Modules\Entries\Entries();
        $entries->recordEntryDetails($prevSubmission->id, $form->id, $data);
        return $prevSubmission->id;
    }

    /**
     * @param $data
     * @param $form
     * @param string $sessionKey
     * @return false|\stdClass|null
     */
    private function maybeSameSubmission($data, $form, $sessionKey = 'user_session_id')
    {
         /*
         * To check your unique session ID here
         * Say your session id value key in the form_body is: user_session_id
         */
         // let's find the previous session id

        if (isset($formData[$sessionKey]) && $userSessionId = $formData[$sessionKey]) {
            $prevSession = wpFluent()->table('fluentform_entry_details')
                ->where('form_id', $form->id)
                ->where('field_name', 'user_session_id')
                ->where('field_value', $userSessionId)
                ->first();

            if ($prevSession) {
                return wpFluent()->table('fluentform_submissions')
                    ->where('form_id', $form->id)
                    ->where('id', $prevSession->submission_id)
                    ->first();
            }
        }

        return false;
    }

}

add_action('init', function () {
    new FfHeadlessServer();
});
