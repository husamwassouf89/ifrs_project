<?php


namespace App\Services;


use App\Models\Client\ClassType;
use App\Models\Client\Client;
use App\Models\Staging\ClientStagingProfile;
use App\Models\Staging\Stage;
use App\Models\Staging\StagingOption;
use App\Models\Staging\StagingOptionResult;
use App\Traits\HelpKit;

class ClientStagingProfileService extends Service
{
    use HelpKit;

    public function calculateStaging($year, $quarter, $client, $grade = null, $info = null)
    {
        if($client->stage_id){
            $stage = Stage::where('id',$client->stage_id)->first();
            return $stage->serial_no;
        }
        $classType = ClassType::find($client->class_type_id);
        if (in_array($classType->sub_category ,['local bank','abroad bank','investments','central banks'])) {
            $list = ['AAA' => 1, 'AA' => 1, 'A' => 1, 'BBB' => 1,
                     'BB'  => 2, 'B' => 2, 'CCC/C' => 2, 'Default' => 3];
            return $list[$grade];
        } else {

            $dateRange = $this->getDateRange($year, $quarter);
            $profile   = ClientStagingProfile::where('client_id', $client->id)
                                             ->where('created_at', '>=', $dateRange['last_date'])
                                             ->orderBy('id', 'desc')
                                             ->with('answers')
                                             ->first();
            if (!$profile) {
                $profile = ClientStagingProfile::where('client_id', $client->id)
                                               ->orderBy('id', 'desc')
                                               ->with('answers')
                                               ->first();
            }

            $stage = 0;
            if ($info and $info->past_due_days) {
                if ($info->past_due_days < 30) {
                    $stage = 1;
                } else if ($info->past_due_days >= 30 and $info->past_due_days < 90) {
                    $stage = 2;
                } else {
                    $stage = 3;
                }

            }

            if ($client->finanical_status != 'None') {
                if ($client->finanical_status == 'Without Financial Data') {
                    $stage = max($stage, 2);
                } else {
                    $stage = max($stage, 1);
                }
            }
            $ok = 1;
            if ($profile and count($profile->answers)) {

                foreach ($profile->answers as $item) {
                    $ok = -1;
                    $results = StagingOptionResult::where('staging_option_id', $item->staging_option_id)->get();
                    foreach ($results as $result) {
                        if ($result->with_range == 'No') {
                            $stage = max($stage, $result->stage_id);
                            break;
                        } else {
                            if ($result->range_start and $result->range_end) {
                                if ($item->value >= $result->range_start and $item->value <= $result->range_end) {
                                    $stage = max($stage, $result->stage_no);
                                }
                            } else if ($result->range_start) {
                                if ($item->value < $result->range_start) {
                                    $stage = max($stage, $result->stage_no);
                                }
                            } else if ($result->range_end) {
                                if ($item->value > $result->range_end) {
                                    $stage = max($stage, $result->stage_no);
                                }
                            }
                        }
                    }
                }
            }
            if($ok)$stage = 'N/A';
        }

        return $stage;
    }

    public function index($id)
    {
        $client = Client::findOrFail($id);
        return ClientStagingProfile::where('client_id', $client->id)->with('answers')->get();
    }

    public function store(array $input)
    {
        $profile = ClientStagingProfile::create(['client_id' => $input['client_id']]);

        foreach ($input['answers'] as $item) {
            $option = StagingOption::find($item['id']);
            $data   = ['staging_option_id' => $item['id']];
            if (isset($item['value']) and $option->with_value == 'Yes') {
                $data['value'] = $item['value'];
            }
            $profile->answers()->create($data);
        }

        return $this->show($profile->id);
    }

    public function show($id)
    {
        return ClientStagingProfile::whereId($id)->with('answers')->first();
    }

    public function destroy($id): bool
    {
        return (bool)ClientStagingProfile::whereId($id)->delete();
    }

}
