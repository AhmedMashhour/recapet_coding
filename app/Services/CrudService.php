<?php

namespace App\Services;

use App\Repositories\Repository;

class CrudService implements ICrudService
{
    protected Repository $repository;
    protected string $entityName;

    public function __construct(string $entityName)
    {
        $this->entityName = strtolower(preg_replace('/\B([A-Z])/', '_$1', $entityName));
        $this->repository = Repository::getRepository($entityName);
    }

    public function create(array $request)
    {
        return $this->repository->create($request);
    }

    public function createMany(array $objects)
    {
        $savedObjects = [];
        foreach ($objects as $object) {
            $savedObjects[] = $this->create($object);
        }

        return $savedObjects;
    }

    public function update(array $request)
    {

        $entity = $this->repository->getById($request['id']);
        if (is_null($entity)) {
            // $output->Error = [__('errors.wrong_identifier')];
            // todo throw error
        }

        return $this->repository->update($entity, $request);
    }

    public function delete(array $request)
    {
        return $this->repository->delete($request['ids']);
    }


    public function getById(array $request)
    {
        return $this->repository->getById($request['id'], $request['related_objects'], $request['related_objects_count']);
    }
}

