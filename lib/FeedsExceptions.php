<?php

// Base exception class for application exceptions
class FeedException extends Exception{}

// Incorrect input
class InputFeedException extends FeedException{}

// AppFeedException not loading
class AppFeedException extends FeedException{}

// URL not loading
class NotLoadingFeedException extends FeedException{}

// Geocoding URL not loading
class NotLoadingGeocodingFeedException extends FeedException{}

// Geocoding failed.
class GeocodingFailedFeedException extends FeedException{}

// Geocoding failed.
class PermissionDeniedFeedException extends FeedException{}