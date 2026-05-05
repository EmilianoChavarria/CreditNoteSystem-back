<?php

namespace App\Services;

use App\Models\RequestType;

class RequestReasonTypeService
{
    public function syncReasons(int $requestTypeId, array $reasonIds): RequestType
    {
        $requestType = RequestType::findOrFail($requestTypeId);

        $requestType->requestReasons()->sync($reasonIds);

        return $requestType->load('requestReasons');
    }
}
