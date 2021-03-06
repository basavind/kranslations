<?php

namespace OCA\CBreeder\Materials;

use OCA\CBreeder\Materials\UndefinedStageException;
use OCP\AppFramework\Db\Entity;

abstract class Material extends Entity
{
    /**
     * Material possible states.
     */
    const STATE_AVAILABLE = 'Доступен';
    const STATE_IN_WORK = 'В работе';
    const STATE_REVERTED = 'Возвращен на доработку';
    const STATE_COMPLETED = 'Завершён';

    /**
     * DB fields.
     */
    protected $name;
    protected $nameSlug;
    protected $section;
    protected $sectionSlug;
    protected $course;
    protected $courseSlug;
    protected $state;
    protected $stage;
    protected $path;
    protected $type;
    protected $class;
    protected $coursePart;

    /**
     * The sequence of material production stages, based on material type.
     * Should be overrided in nested typed classes.
     *
     * @return array
     */
    public static function getStages()
    {
        return static::$stages;
    }

    /**
     * @param int $id
     *
     * @return string
     */
    public function getStageAt($id)
    {
        if ($id >= count(static::$stages) || $id < 0) {
            throw new UndefinedStageException();
        }

        return static::$stages[$id];
    }

    /**
     * @return string
     */
    public function getLastStage()
    {
        return static::$stages[count(static::$stages) - 1];
    }

    public function __construct()
    {
        $this->addType('section', 'string');
        $this->addType('sectionSlug', 'string');
        $this->addType('course', 'string');
        $this->addType('courseSlug', 'string');
        $this->addType('name', 'string');
        $this->addType('nameSlug', 'string');
        $this->addType('path', 'string');
        $this->addType('coursePart', 'string');
        $this->addType('stage', 'string');
        $this->addType('state', 'string');
        $this->addType('type', 'string');
        $this->addType('class', 'string');

        $this->init();
    }

    private function init()
    {
        if ( ! count(self::getStages())) {
            throw new \LogicException('Class does not realize production scenario!');
        }

        $this->setStage(self::getStageAt(0));
        $this->setState(self::STATE_AVAILABLE);
        $this->setClass(get_class($this));
    }

    /**
     * Update material stage up to the next one, accordingly to material type.
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function stageUp()
    {
        if ( ! $this->updateStage('up', self::STATE_AVAILABLE)) {
            throw \Exception('An unexpected error occurred while attempting to change the stage of the material!');
        }

        return $this;
    }

    /**
     *  Update material stage down to the next one, accordingly to material type.
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function stageDown()
    {
        if ( ! $this->updateStage('down', self::STATE_REVERTED)) {
            throw \Exception('An unexpected error occurred while attempting to change the stage of the material!');
        }

        return $this;
    }

    /**
     * Update material stage.
     *
     * @param string $direction
     * @param string $state
     *
     * @return bool
     *
     * @throws \Exception
     */
    protected function updateStage($direction, $state) //TODO Делать проверку на роль - иначе стейдж может "проскользнуть"
    {
        $stageKey = array_search($this->stage, self::getStages());

        switch ($direction) {
            case 'up':
                $newKey = $stageKey + 1;
                break;
            case 'down':
                $newKey = $stageKey - 1;
                break;
            default:
                $newKey = $stageKey;
        }

        if (key_exists($newKey, self::getStages())) {
            $newStage = self::getStageAt($newKey);
            $this->setStage($newStage);
            $this->setState($state);
        } else {
            throw new UndefinedStageException('The material stage does not exist!');
        }

        return true;
    }

    /**
     * Maps the keys of the row array to the attributes.
     *
     * @param array $row the row to map onto the entity
     *
     * @return object
     *
     * @throws \Exception
     *
     * @since 7.0.0
     */
    public static function fromRow(array $row)
    {
        if ( ! isset($row['class']) || ! class_exists($row['class'])) {
            throw new \Exception('Material type class does not exists!');
        }

        $class = $row['class'];
        $instance = new $class();

        foreach ($row as $key => $value) {
            $prop = ucfirst($instance->columnToProperty($key));
            $setter = 'set'.$prop;
            $instance->$setter($value);
        }

        $instance->resetUpdatedFields();

        return $instance;
    }

    /**
     * Returns db properties as assoc array.
     * 
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'name_slug' => $this->getNameSlug(),
            'course' => $this->getCourse(),
            'course_slug' => $this->getCourseSlug(),
            'section' => $this->getSection(),
            'section_slug' => $this->getSectionSlug(),
            'state' => $this->getState(),
            'stage' => $this->getStage(),
            'stages' => $this->getStages(),
            'class' => $this->getClass(),
            'type' => $this->getType(),
            'course_part' => $this->getCoursePart(),
        ];
    }
}
