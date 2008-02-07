<?php
declare(encoding = 'utf-8');

/*                                                                        *
 * This script is part of the TYPO3 project - inspiring people to share!  *
 *                                                                        *
 * TYPO3 is free software; you can redistribute it and/or modify it under *
 * the terms of the GNU General Public License version 2 as published by  *
 * the Free Software Foundation.                                          *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
 * Public License for more details.                                       *
 *                                                                        */ 

/**
 * The default TYPO3 Package Manager
 * 
 * @package		FLOW3
 * @subpackage	Package
 * @version 	$Id:T3_FLOW3_Package_Manager.php 203 2007-03-30 13:17:37Z robert $
 * @copyright	Copyright belongs to the respective authors
 * @license		http://opensource.org/licenses/gpl-license.php GNU Public License, version 2
 */
class T3_FLOW3_Package_Manager implements T3_FLOW3_Package_ManagerInterface {

	/**
	 * @var T3_FLOW3_Component_ManagerInterface	Holds an instance of the component manager
	 */
	protected $componentManager;

	/**
	 * @var array			Array of available packages, indexed by package key
	 */
	protected $packages = array();

	/**
	 * @var array			List of active packages - not used yet!
	 */
	protected $arrayOfActivePackages = array();
		
	/**
	 * @var array			Array of packages whose classes must not be registered as components automatically
	 */
	protected $componentRegistrationPackageBlacklist = array();

	/**
	 * @var array			Array of class names which must not be registered as components automatically. Class names may also be regular expressions.
	 */
	protected $componentRegistrationClassBlacklist = array(
		'T3_FLOW3_AOP_.*',
		'T3_FLOW3_Component.*',
		'T3_FLOW3_Package.*',
		'T3_FLOW3_Reflection.*',
	);
	
	/**
	 * @var T3_FLOW3_Package_ComponentsConfigurationSourceInterface	$packageComponentsConfigurationSourceObjects: An array of component configuration source objects which deliver the components configuration for a package
	 */
	protected $packageComponentsConfigurationSourceObjects = array();
	
	/**
	 * Constructor
	 * 
	 * @param   T3_FLOW3_Component_ManagerInterface	$componentManager: An instance of the component manager
	 * @return	void
	 * @author  Robert Lemke <robert@typo3.org>
	 */
	public function __construct(T3_FLOW3_Component_ManagerInterface $componentManager) {
		$this->componentManager = $componentManager;
		$this->registerFLOW3Components();
		$this->packageComponentsConfigurationSourceObjects = array (
			$this->componentManager->getComponent('T3_FLOW3_Package_ConfFileComponentsConfigurationSource'),
			$this->componentManager->getComponent('T3_FLOW3_Package_PHPFileComponentsConfigurationSource')
		);
	}

	/**
	 * Initializes the package manager
	 *
	 * @return	void
	 * @author	Robert Lemke <robert@typo3.org>
	 */
	public function initialize() {
		$this->buildPackageRegistry();
		$this->registerAndConfigureAllPackageComponents();
	}

	/**
	 * Returns TRUE if a package is available (the package's files exist in the pcakages directory)
	 * or FALSE if it's not. If a package is available it doesn't mean neccessarily that it's active!
	 *
	 * @param	string		$packageKey: The key of the package to check
	 * @return	boolean		TRUE if the package is available, otherwise FALSE
	 * @author	Robert Lemke <robert@typo3.org> 
	 */
	public function isPackageAvailable($packageKey) {
		if (!is_string($packageKey)) throw new InvalidArgumentException('The package key must be of type string, ' . gettype($packageKey) . ' given.', 1200402593);
		return (key_exists($packageKey, $this->packages));
	}
	
	/**
	 * Returns a T3_FLOW3_Package_PackageInterface object for the specified package.
	 * A package is available, if the package directory contains valid meta information.
	 *
	 * @param  string					$packageKey
	 * @return T3_FLOW3_Package	The requested package object
	 * @throws T3_FLOW3_Package_Exception_UnknownPackage if the specified package is not known
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getPackage($packageKey) {
		if (!$this->isPackageAvailable($packageKey)) throw new T3_FLOW3_Package_Exception_UnknownPackage('Package "' . $packageKey . '" is not available.', 1166546734);
		return $this->packages[$packageKey];
	}
	
	/**
	 * Returns an array of T3_FLOW3_Package_Meta objects of all available packages.
	 * A package is available, if the package directory contains valid meta information.
	 *
	 * @return	array		Array of T3_FLOW3_Package_Meta
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getPackages() {
		return $this->packages;
	}
	
	/**
	 * Returns the absolute path to the root directory of a package. Only
	 * works for package which have a proper meta.xml file - which they 
	 * should.
	 * 
	 * @param	string		$packageKey: Name of the package to return the path of
	 * @return	string		Absolute path to the package's root directory 
	 * @throws T3_FLOW3_Package_Exception_UnknownPackage if the specified package is not known
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getPackagePath($packageKey) {
		if (!$this->isPackageAvailable($packageKey)) throw new T3_FLOW3_Package_Exception_UnknownPackage('Package "' . $packageKey . '" is not available.', 1166543253);
		return $this->packages[$packageKey]->getPackagePath();
	}
	
	/**
	 * Returns the absolute path to the "Classes" directory of a package.
	 * 
	 * @param	string		$packageKey: Name of the package to return the "Classes" path of
	 * @return	string		Absolute path to the package's "Classes" directory, with trailing directory separator
	 * @throws T3_FLOW3_Package_Exception_UnknownPackage if the specified package is not known
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getPackageClassesPath($packageKey) {
		if (!$this->isPackageAvailable($packageKey)) throw new T3_FLOW3_Package_Exception_UnknownPackage('Package "' . $packageKey . '" is not available.', 1167574237);
		return $this->packages[$packageKey]->getClassesPath();
	}
	
	/**
	 * Registers certain classes of the Package Manager as components, so they can
	 * be used for dependency injection elsewhere.
	 * 
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	protected function registerFLOW3Components() {
		$this->componentManager->registerComponent('T3_FLOW3_Package_Package', 'T3_FLOW3_Package_Package');
		$this->componentManager->registerComponent('T3_FLOW3_Package_ConfFileComponentsConfigurationSource');
		$this->componentManager->registerComponent('T3_FLOW3_Package_PHPFileComponentsConfigurationSource');

		$componentConfigurations = $this->componentManager->getComponentConfigurations();
		$componentConfigurations['T3_FLOW3_Package_Package']->setScope('prototype');
		$this->componentManager->setComponentConfigurations($componentConfigurations);
	}
	
	/**
	 * Scans all directories in the Packages/ directory for available packages.
	 * For each package a T3_FLOW3_Package_ object is created which is then
	 * stored in the $this->packages array.
	 * 
	 * @return  void
	 * @author  Robert Lemke <robert@typo3.org>
	 */
	protected function buildPackageRegistry() {
		$availablePackagesArr = array();

		$packagesDirectoryIterator = new DirectoryIterator(TYPO3_PATH_PACKAGES);			
		while ($packagesDirectoryIterator->valid()) {
			$filename = $packagesDirectoryIterator->getFilename();
			if ($filename{0} != '.') {
				$availablePackagesArr[$filename] = $this->componentManager->getComponent('T3_FLOW3_Package_Package', $filename, ($packagesDirectoryIterator->getPathName() . '/'), $this->packageComponentsConfigurationSourceObjects);
			}
			$packagesDirectoryIterator->next();
		}			
		$this->packages = $availablePackagesArr;
	}
	

	/**
	 * Traverses through all active packages and registers their classes as
	 * components at the component manager. Finally the component configuration
	 * defined by the package is loaded and applied to the registered components.
	 * 
	 * @return  void
	 * @author  Robert Lemke <robert@typo3.org>
	 */
	protected function registerAndConfigureAllPackageComponents() {
		$componentTypes = array();
		
		foreach ($this->packages as $packageKey => $package) {
				// For now (during development) removed this condition from the following line: array_search($packageKey, $this->arrayOfActivePackages) !== FALSE
			$packageIsActiveAndNotBlacklisted = (array_search($packageKey, $this->componentRegistrationPackageBlacklist) === FALSE);
			if ($packageIsActiveAndNotBlacklisted) {
				foreach ($package->getClassFiles() as $className => $relativePathAndFilename) {
					if (!$this->classNameIsBlacklisted($className)) {
						if (T3_PHP6_Functions::substr($className, -9, 9) == 'Interface') {
							$componentTypes[] = $className;
							if (!$this->componentManager->isComponentRegistered($className)) {
								$this->componentManager->registerComponentType($className);
							}
						}
					}
				}
			}
		}		
	
		$masterComponentConfigurations = $this->componentManager->getComponentConfigurations();
		foreach ($this->packages as $packageKey => $package) {
				// For now (during development) removed this condition from the following line: array_search($packageKey, $this->arrayOfActivePackages) !== FALSE
			$packageIsActiveAndNotBlacklisted = (array_search($packageKey, $this->componentRegistrationPackageBlacklist) === FALSE);
			if ($packageIsActiveAndNotBlacklisted) {
				foreach ($package->getClassFiles() as $className => $relativePathAndFilename) {
					if (!$this->classNameIsBlacklisted($className)) {
						if (T3_PHP6_Functions::substr($className, -9, 9) != 'Interface') {
							$componentName = $className;
							if (!$this->componentManager->isComponentRegistered($componentName)) {
								$class = new T3_FLOW3_Reflection_Class($className);
								if (!$class->isAbstract()) {
									$this->componentManager->registerComponent($componentName, $className);
								}
							}
						}
					}
				}
				foreach ($package->getComponentConfigurations() as $componentName => $componentConfiguration) {
					if (!$this->componentManager->isComponentRegistered($componentName)) {
						throw new T3_FLOW3_Package_Exception_InvalidComponentConfiguration('Tried to configure unknown component "' . $componentName . '" in package "' . $package->getPackageKey() . '". The configuration came from ' . $componentConfiguration->getConfigurationSourceHint() .'.', 1184926175);
					}
					$masterComponentConfigurations[$componentName] = $componentConfiguration;
				}
			}
		}		

		foreach ($componentTypes as $componentType) {
			$defaultImplementationClassName = $this->componentManager->getDefaultImplementationClassNameForInterface($componentType);
			if ($defaultImplementationClassName !== FALSE) {
				$masterComponentConfigurations[$componentType]->setClassName($defaultImplementationClassName);
			}
		}

		$this->componentManager->setComponentConfigurations($masterComponentConfigurations);		
	}
	
	/**
	 * Checks if the given class name appears on in the component blacklist.
	 * 
	 * @param  string		$className: The class name to check. May be a regular expression.
	 * @return boolean		TRUE if the class has been blacklisted, otherwise FALSE
	 * @author Robert Lemke <robert@typo3.org>
	 */
	protected function classNameIsBlacklisted($className) {
		$isBlacklisted = FALSE;
		foreach ($this->componentRegistrationClassBlacklist as $blacklistedClassName) {
			if ($className == $blacklistedClassName || preg_match('/^' . $blacklistedClassName . '$/', $className)) {
				$isBlacklisted = TRUE;
			}
		}
		return $isBlacklisted;
	}
}

?>