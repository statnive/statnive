Feature: Avg Duration Data Pipeline
  As a site owner
  I want page view durations to be accurately aggregated
  So that Avg Duration in my dashboard shows real engagement data

  Background:
    Given a WordPress site with Statnive activated
    And no prior analytics data exists

  Scenario: Engagement duration flows from tracker to views table
    Given a visitor loads the homepage
    And the visitor stays on the page for at least 3 seconds
    When the browser fires visibilitychange (hidden)
    Then the engagement endpoint should receive duration > 0
    And the views table should have a row with duration > 0
    And the sessions table duration should remain 0

  Scenario: Aggregation reads duration from views table
    Given a visitor has viewed a page with engagement_time = 45
    And the view record has duration = 45
    When daily aggregation runs for that date
    Then summary.total_duration should equal 45
    And summary_totals.total_duration should equal 45

  Scenario: Dashboard shows non-zero avg duration
    Given multiple visitors have viewed pages with engagement data
    When I view the dashboard for today's date range
    Then total_duration in the API response should be greater than 0

  Scenario: Zero engagement produces zero duration
    Given a visitor loads a page but leaves immediately (no engagement sent)
    When daily aggregation runs
    Then summary.total_duration should equal 0

  Scenario: Mixed engagement across views aggregates correctly
    Given 3 page views exist with durations 0, 30, and 60 seconds
    When daily aggregation runs
    Then summary_totals.total_duration should equal 90
