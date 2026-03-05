# Solution / ADR
## IT'S OVERCOMPLICATED!

### Assumptions
* I did not use full Symfony, I decided not to configure the whole HTTP Kernel and DI
* instead of a real DI container I used the Factory pattern to build object instances; in a real system the framework would handle this for me
* tests do not have fully separated base classes; in this project it is not a problem, but in a real system I would like to have `UnitTestCase` and `FunctionalTestCase` with a local testing framework around them
* I used the circuit breaker pattern to better manage communication with external API
* calculations from API and manual system calculations are different implementations of the same policy for me
* a concrete calculation policy is always persisted to database if it does not return an error
* some policies may require refresh; this is defined by another policy: `\App\Domain\Policy\Refresh\RequiresRefreshPolicy`
* each policy can define its failover, meaning which next policy should be used in case of error
* full coverage with static analysis tools: phpstan, cs-fixer, psalm and mutation tests

### Flow
* request goes into `\App\Presentation\Http\HttpApplication::run`
* it is validated there and transformed into command
* service `\App\Application\UseCase\FindBoxSize` is called, this is the use case and it orchestrates the process of finding the smallest available box
* we build unique hash based on product IDs (I assume that changing product dimensions in the system means creating a new product; other behavior seems very unsafe to me); this must be a special hash because products can be added to cart in different order and calculated box is still the same
* based on hash we search for record in database
  * if it exists, we check if it needs refresh using `RequiresRefreshPolicy`
    * important thing: I track changes and log each one, whether refresh helped, nothing changed, or another implementation makes false positive; this is very important metric for me
    * correct calculations are critical functionality in this system, so if one metric says boxes exist and another says they do not, this is very dangerous situation
    * I do not yet check if regression also concerns box size; in real system this should be expanded
  * if it does not exist, we calculate from zero using `CalculateBoxSize` service
* box calculation policies may not finish correctly; policy itself defines fallback and this continues while it is possible
* in current example there are only two implementations, but in real system I assume there can be more, e.g. 2 competing providers and manual calculation; then provider A has fallback to B and B has fallback to C
* all API communication is validated and written to logs and guarded by circuit breaker
* I decided for this pattern because integrations with external providers require proper protection; for example if API returns 500, we do not want to call it again immediately, only after some time
* we should not overload API because provider may not be able to handle traffic; firing more requests will only make situation worse
* circuit breaker immediately redirects user to currently working implementation

### What I would do differently in real system
* I would make this much simpler; I would probably know traffic, change frequency and could align with business whether assumptions are correct and adequate for timeline of the microservice; now I do not know if this is MVP or enterprise product
* I would still choose circuit breaker because for this kind of integrations it is very useful
* tests need improvement: full testing framework is missing where I could separate specific test cases; in this project it was not required
* logging and errors, including API errors, are not yet fully transparent in the app
* I wanted to focus on the most important logs and metrics; this is very important part for me and I wanted to highlight this priority
* in real system, together with business, I would choose metrics and logging scope to ensure stability and ability to react to how system behaves from business perspective, and I would also work on specific Exception classes
* I would improve circuit breaker timings to more realistic values; currently they are default so I do not focus on this now, but in real solution I would find values that fit given API best
* I would move fallback definition outside of policy; I implemented it inside policy to move faster in this task, but this is not a good long-term practice and should be configured at application/orchestration level
