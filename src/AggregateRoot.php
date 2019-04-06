<?php

namespace Spatie\EventProjector;

use Illuminate\Support\Str;
use Spatie\EventProjector\Models\StoredEvent;

abstract class AggregateRoot
{
    /** @var array */
    private $recordedEvents = [];

    /** @var string */
    private $uuid;

    public static function retrieve(string $uuid): AggregateRoot
    {
        $aggregateRoot =  (new static());

        $aggregateRoot->uuid = $uuid;

        return $aggregateRoot->reconstituteFromEvents();
    }

    public function persist(): AggregateRoot
    {
        $this->registerEventHandlers();

        collect($this->recordedEvents())->each(function(DomainEvent $newDomainEvent) {
            $this->getProjectionist()->storeEvent($newDomainEvent, $this->uuid);
        });

        return $this;
    }

    public function recordThat(DomainEvent $domainEvent): AggregateRoot
    {
        $this->recordedEvents[] = $domainEvent;

        $this->apply($domainEvent);

        return $this;
    }

    private function recordedEvents(): array
    {
        $recordedEvents = $this->recordedEvents;

        $this->recordedEvents = [];

        return $recordedEvents;
    }

    private function reconstituteFromEvents(): AggregateRoot
    {
        StoredEvent::uuid($this->uuid)->each(function (StoredEvent $storedEvent) {
            $this->apply($storedEvent->event);
        });

        return $this;
    }

    private function apply(DomainEvent $event): void
    {
        $classBaseName = class_basename($event);

        $camelCasedBaseName = ucfirst(Str::camel($classBaseName));

        $applyingMethodName = "apply{$camelCasedBaseName}";

        if (method_exists($this, $applyingMethodName)) {
            $this->$applyingMethodName($event);
        }
    }

    private function getProjectionist(): Projectionist
    {
        return app(Projectionist::class);
    }

    private function registerEventHandlers()
    {
        $this->getProjectionist()
            ->addProjectors($this->projectors ?? [])
            ->addReactors($this->reactors ?? []);
    }
}
