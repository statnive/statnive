Feature: Pageview Recording
  As a site owner
  I want visitor pageviews to be recorded automatically
  So that I can see which pages are visited

  Scenario: First pageview creates visitor, session, and view records
    Given a WordPress site with Statnive activated
    And no prior analytics data exists
    When a visitor loads the homepage
    Then the tracker script should be present in the page source
    And a hit request should be sent to the REST API
    And the REST API should respond with 204
    And the statnive_visitors table should contain exactly 1 row
    And the statnive_sessions table should contain exactly 1 row
    And the statnive_views table should contain exactly 1 row

  Scenario: Second pageview from same visitor reuses visitor record
    Given a visitor has already viewed the homepage
    When the same visitor loads the about page
    Then the statnive_visitors table should still contain exactly 1 row
    And the statnive_sessions table should contain exactly 1 row
    And the statnive_views table should contain exactly 2 rows

  Scenario: DNT header prevents tracking
    Given DNT respect is enabled in Statnive settings
    When a visitor with DNT=1 header loads the homepage
    Then no hit request should be sent
    And no database records should be created

  Scenario: Invalid HMAC signature is rejected
    When a request with a tampered signature is sent to the hit endpoint
    Then the REST API should respond with 403
    And no database records should be created
