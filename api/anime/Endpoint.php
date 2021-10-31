<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime;

// Describes the necessary functionality for an endpoint that's able to handle an incoming request.
// Split up in two methods: input validation, and endpoint activation.
interface Endpoint {
    // Validates the given input data. Should check for required input, formatting, as well as
    // length and syntax requirements. A default implementation is deliberately omitted. An error
    // message may be returned, which will most likely be displayed to the client.
    public function validateInput(array $requestParameters, array $requestData): bool | string;

    // Executes this endpoint considering the given |$api| and input data. An array should be
    // returned, which will be the response value of the API call.
    public function execute(Api $api, array $requestParameters, array $requestData): array;
}
