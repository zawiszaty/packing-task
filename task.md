API Packaging Calculation

The goal is to implement a microservice that will handle the calculation of the most suitable box defined by its internal dimensions for a set of products also defined by their internal dimensions.

You will not be implementing the calculation itself but you will use this API for that.
https://www.3dbinpacking.com/en/loading-optimization-software/pack-a-shipment (Pack a Shipment). Create an account (use some temporary email) and you will get a testing API key in there.

Since the service has a strict rate limit, save results so we don’t call the API for the same input twice, but we use previous calculation.

Did you work with Guzzle? If so, use Guzzle for HTTP
Did you work with Doctrine? If so, use it for storing previous results into a database.
Focus mainly on nice, testable and robust code. Do not forget error handling and validation
Keep in mind that this service will be used in the customer’s shopping cart in order to estimate shipping costs. Think of edge cases that can occur in this environment.
Use some simple local package calculation when the 3rd party API is down (fallback solution)


Input:
List of products [{width, height, length, weight}]


Backend configuration:
List of boxes [{width, height, length, maxWeight}]


Output:
Smallest box that we can use for products.

As a result, we should have an app that will be able to receive the input above in JSON and will send the single smallest usable box on output. If not packable to a single box, don't return multiple boxes. Products can be rotated in any direction. Start with a prepared application https://github.com/janedbal/shipmonk-packing-stub (do not fork it).

Before evaluation, push your solution to some publicly-available git repository.
