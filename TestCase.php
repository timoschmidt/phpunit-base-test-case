<?php

namespace Morphodo\PHPUnit;

/**
 * Provides general testing methods for mocking.
 *
 * @package Morphodo\Tests
 */
class TestCase extends \PHPUnit_Framework_TestCase {

	/**
	 * @var string
	 */
	protected $fixtureBasePath = '';

	/**
	 * Set the default fixture base path for the current test case class.
	 * This method should be called in the "setup()" method.
	 *
	 * <example>
	 *   $this->setFixtureBasePath(__DIR__);
	 * </example>
	 *
	 * @param $fixtureBasePath
	 * @return void
	 */
	public function setFixtureBasePath($fixtureBasePath) {
		$this->fixtureBasePath = $fixtureBasePath;
	}

	/**
	 * Returns the content of a given fixture file.
	 *
	 * @param string $fixture Relative path to the fixture file.
	 * @throws \PHPUnit_Framework_Exception
	 * @return string
	 */
	protected function getFixtureContent($fixture) {
		$fixture = '/' . ltrim($fixture, '/');
		$fixture = str_replace('/', DIRECTORY_SEPARATOR, $fixture);
		$fixtureFilePath = $this->fixtureBasePath . $fixture;
		if (is_file($fixtureFilePath)) {
			$fixtureContent = file_get_contents($fixtureFilePath);
		} else {
			throw new \PHPUnit_Framework_Exception('Fixture file: "' . $fixtureFilePath . '" not found!');
		}

		return $fixtureContent;
	}

	/**
	 * Returns a mock object which allows for calling protected methods and access
	 * of protected properties.
	 *
	 * @param string $originalClassName Full qualified name of the original class
	 * @param array $methods
	 * @param array $arguments
	 * @param string $mockClassName
	 * @param boolean $callOriginalConstructor
	 * @param boolean $callOriginalClone
	 * @param boolean $callAutoload
	 * @return \PHPUnit_Framework_MockObject_MockObject
	 * @api
	 */
	protected function getAccessibleMock($originalClassName, $methods = array(), array $arguments = array(), $mockClassName = '', $callOriginalConstructor = TRUE, $callOriginalClone = TRUE, $callAutoload = TRUE) {
		return $this->getMock($this->buildAccessibleProxy($originalClassName), $methods, $arguments, $mockClassName, $callOriginalConstructor, $callOriginalClone, $callAutoload);
	}

	/**
	 * Returns a mock object which allows for calling protected methods and access
	 * of protected properties.
	 *
	 * @param string $originalClassName Full qualified name of the original class
	 * @param array $arguments
	 * @param string $mockClassName
	 * @param boolean $callOriginalConstructor
	 * @param boolean $callOriginalClone
	 * @param bool $callAutoload
	 * @return \PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getAccessibleMockForAbstractClass($originalClassName, array $arguments = array(), $mockClassName = '', $callOriginalConstructor = TRUE, $callOriginalClone = TRUE, $callAutoload = TRUE) {
		return $this->getMockForAbstractClass($this->buildAccessibleProxy($originalClassName), $arguments, $mockClassName, $callOriginalConstructor, $callOriginalClone, $callAutoload);
	}

	/**
	 * Creates a proxy class of the specified class which allows
	 * for calling even protected methods and access of protected properties.
	 *
	 * @param string $className Full qualified name of the original class
	 * @return string Full qualified name of the built class
	 */
	protected function buildAccessibleProxy($className) {
		$accessibleClassName = 'AccessibleTestProxy' . md5(uniqid(mt_rand(), TRUE));
		$class = new \ReflectionClass($className);
		$abstractModifier = $class->isAbstract() ? 'abstract ' : '';
		eval('
			' . $abstractModifier . 'class ' . $accessibleClassName . ' extends ' . $className . ' {
				public function _call($methodName) {
					return call_user_func_array(array($this, $methodName), array_slice(func_get_args(), 1));
				}
				public function _callRef($methodName, &$arg1 = NULL, &$arg2 = NULL, &$arg3 = NULL, &$arg4 = NULL, &$arg5= NULL, &$arg6 = NULL, &$arg7 = NULL, &$arg8 = NULL, &$arg9 = NULL) {
					switch (func_num_args()) {
						case 0 : return $this->$methodName();
						case 1 : return $this->$methodName($arg1);
						case 2 : return $this->$methodName($arg1, $arg2);
						case 3 : return $this->$methodName($arg1, $arg2, $arg3);
						case 4 : return $this->$methodName($arg1, $arg2, $arg3, $arg4);
						case 5 : return $this->$methodName($arg1, $arg2, $arg3, $arg4, $arg5);
						case 6 : return $this->$methodName($arg1, $arg2, $arg3, $arg4, $arg5, $arg6);
						case 7 : return $this->$methodName($arg1, $arg2, $arg3, $arg4, $arg5, $arg6, $arg7);
						case 8 : return $this->$methodName($arg1, $arg2, $arg3, $arg4, $arg5, $arg6, $arg7, $arg8);
						case 9 : return $this->$methodName($arg1, $arg2, $arg3, $arg4, $arg5, $arg6, $arg7, $arg8, $arg9);
					}
				}
				public function _set($propertyName, $value) {
					$this->$propertyName = $value;
				}
				public function _setRef($propertyName, &$value) {
					$this->$propertyName = $value;
				}
				public function _get($propertyName) {
					return $this->$propertyName;
				}
			}
		');
		return $accessibleClassName;
	}

	/**
	 * Injects $dependency into property $name of $target
	 *
	 * This is a convenience method for setting a protected or private property in
	 * a test subject for the purpose of injecting a dependency.
	 *
	 * @param object $target The instance which needs the dependency
	 * @param string $name Name of the property to be injected
	 * @param object $dependency The dependency to inject usually an object but can also be any other type
	 * @return void
	 * @throws \RuntimeException
	 * @throws \InvalidArgumentException
	 */
	protected function inject($target, $name, $dependency) {
		if (!is_object($target)) {
			throw new \InvalidArgumentException('Wrong type for argument $target, must be object.');
		}

		$objectReflection = new \ReflectionObject($target);
		$methodNamePart = strtoupper($name[0]) . substr($name, 1);
		if ($objectReflection->hasMethod('set' . $methodNamePart)) {
			$methodName = 'set' . $methodNamePart;
			$target->$methodName($dependency);
		} elseif ($objectReflection->hasMethod('inject' . $methodNamePart)) {
			$methodName = 'inject' . $methodNamePart;
			$target->$methodName($dependency);
		} elseif ($objectReflection->hasProperty($name)) {
			$property = $objectReflection->getProperty($name);
			$property->setAccessible(TRUE);
			$property->setValue($target, $dependency);
		} else {
			throw new \RuntimeException('Could not inject ' . $name . ' into object of type ' . get_class($target));
		}
	}

	/**
	 * Helper method to create a completely mocked object where no constructor gets called.
	 * Should be used when a mock without any side effects is needed.
	 *
	 * @param $className
	 * @return object
	 */
	protected function getMutedMock($className) {
		return $this->getMock($className, array(), array(), '', false);
	}
}
