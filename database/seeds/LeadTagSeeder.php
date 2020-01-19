<?php

use App\Models\LeadTag;
use Illuminate\Database\Seeder;

class LeadTagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $inserts = [
            ["campaign_id" => 0, "name" => "walk-in", "text" => "Lead came in", "indication" => "positive"],
            ["campaign_id" => 0, "name" => "will-come-in", "text" => "Lead will come in", "indication" => "positive"],
            ["campaign_id" => 0, "name" => "serviced", "text" => "Serviced their vehicle", "indication" => "positive"],
            ["campaign_id" => 0, "name" => "future-lead", "text" => "Interested but not just yet", "indication" => "neutral"],
            ["campaign_id" => 0, "name" => "purchased", "text" => "Purchased a vehicle", "indication" => "positive"],
            ["campaign_id" => 0, "name" => "suppress", "text" => "Never wants to be contacted", "indication" => "negative"],
            ["campaign_id" => 0, "name" => "heat-prior", "text" => "Lead upset over prior experience", "indication" => "negative"],
            ["campaign_id" => 0, "name" => "heat-current", "text" => "Lead upset over current experience", "indication" => "negative"],
            ["campaign_id" => 0, "name" => "old-data-vehicle", "text" => "Lead no longer owns vehicle", "indication" => "negative"],
            ["campaign_id" => 0, "name" => "wrong-data-vehicle", "text" => "Lead never owned vehicle", "indication" => "negative"],
            ["campaign_id" => 0, "name" => "old-data-address", "text" => "Lead moved out of the area", "indication" => "negative"],
            ["campaign_id" => 0, "name" => "wrong-lead-identity-phone", "text" => "Wrong Number", "indication" => "negative"],
            ["campaign_id" => 0, "name" => "wrong-lead-identity-email", "text" => "Wrong Email Address", "indication" => "negative"],
            ["campaign_id" => 0, "name" => "deceased", "text" => "Recipient is deceased", "indication" => "negative"],
        ];
        if (\App\Models\LeadTag::where('name', 'checked-in-from-text-to-value')->count() === 0) {
            $inserts[] = [
                'name' => 'checked-in-from-text-to-value',
                'text' => 'Checked in from text-to-value feature',
                'indication' => 'feature',
                'campaign_id' => 0
            ];
        }
        if (\App\Models\LeadTag::where('name', 'vehicle-value-request-using-text-to-value')->count() === 0) {
            $inserts[] = [
                'name' => 'vehicle-value-request-using-text-to-value',
                'text' => 'Requested vehicle value using text-to-value feature',
                'indication' => 'feature',
                'campaign_id' => 0
            ];
        }
        LeadTag::insert($inserts);
    }
}
