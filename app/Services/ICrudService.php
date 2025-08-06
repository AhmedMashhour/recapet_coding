<?php

namespace App\Services;

interface ICrudService{
    public function create(array $request);

    public function update(array $request) ;

    public function delete(array $request);

    public function getById(array $request);

}
