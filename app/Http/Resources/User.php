<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class User extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'is_admin' => (int) $this->is_admin,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
//            'companies' => $this->when(auth()->user()->isAdmin(), function () {
//                return Company::collection($this->companies);
//            }),
            'is_active' => $this->when(!auth()->user()->isAdmin(), function () {
                return (bool) $this->resource->isActive(get_active_company());
            }),
            'role' => $this->when(!auth()->user()->isAdmin(), function () {
                return $this->resource->getRole(\App\Models\Company::findOrFail(get_active_company()));
            }),
            'active_companies' => $this->countActiveCompanies(),
            'has_active_companies' => $this->hasActiveCompanies(),
            'has_pending_invitations' => $this->hasPendingInvitations()
        ];
    }
}
