# Registratieformulier

Deel via dit formulier je belangstelling om als gopher mee te helpen tijdens Anime 2018.

<p style="display: none" id="error" class="error">Eén van de velden was niet goed ingevuld, sorry! Probeer het nog eens.</p>
<script>
if (document.location.hash == '#error')
  document.getElementById('error').style.display = 'block';
</script>

<form action="registratie.php" method="post">
  <ol>
    <li>
      <label for="naam">Naam:</label>
      <input type="text" required name="naam" id="naam" />
    </li>
    <li>
      <label for="geboortedatum">Geboortedatum:</label>
      <input type="date" required name="geboortedatum" id="geboortedatum" />
    </li>
    <li>
      <label for="email">E-mail adres:</label>
      <input type="text" required name="email" id="email" />
    </li>
    <li>
      <label for="telefoonnummer">Telefoonnummer:</label>
      <input type="text" required name="telefoonnummer" id="telefoonnummer" />
    </li>
  </ol>

<span style="color: #883133">▼</span> Welke taak heeft je voorkeur?

  <ol>
    <li>
      <label for="tech">Technisch werk</label>
      <input type="checkbox" name="tech" id="tech" checked />
    </li>
    <li>
      <label for="desk">Registratiebalie</label>
      <input type="checkbox" name="desk" id="desk" checked />
    </li>
    <li>
      <label for="events">Events</label>
      <input type="checkbox" name="events" id="events" checked />
    </li>
    <li>
      <label for="cloakroom">Garderobe</label>
      <input type="checkbox" name="cloakroom" id="cloakroom" checked />
    </li>
  </ol>

<span style="color: #883133">▼</span> En ten slotte enkele korte vragen over je voorkeuren betreft
inzet tijdens Anime 2018.

  <ol>
    <li>
      <label for="aanwezig">Ben je het hele weekend aanwezig? <sup>1</sup></label>
      <input type="checkbox" name="aanwezig" id="aanwezig" />
    </li>
    <li>
      <label for="location">Waar verblijf je tijdens de con?</label>
      <input type="text" required name="location" id="location" />
    </li>
    <li>
      <label for="night">Kan je ook een nachtshift oppakken? <sup>2</sup></label>
      <input type="checkbox" name="night" id="night" checked />
    </li>
    <li>
      <label for="tshirt">Welke t-shirtmaat heb je?</label>
      <select name="tshirt" id="tshirt">
        <option>S</option>
        <option selected>M</option>
        <option>L</option>
        <option>XL</option>
        <option>XXL</option>
      </select>
    </li>
    <li>
      <label for="girly">... in een girly fit?</label>
      <input type="checkbox" name="girly" id="girly" />
    </li>
    <li>
      <label for="ticket">Heb je al een kaartje? <sup>3</sup></label>
      <input type="checkbox" name="ticket" id="ticket" />
    </li>
    <li>
      <label for="social">Wil je in onze social media groepen? <sup>4</sup></label>
      <input type="checkbox" name="social" id="social" />
    </li>
  </ol>

  <input type="submit" value="Verzenden" />
</form>

<sup>1 -</sup> We verwachten dat je tussen 10 uur 's ochtends op vrijdag en 6 uur 's avonds op
zondag aanwezig kunt zijn.

<sup>2 -</sup> Gezien het festival ook 's nachts doorloopt zullen we een aantal vrijwilligers
vragen om ook een nachtshift te draaien.

<sup>3 -</sup> We vragen je om <a href="https://tickets.animecon.nl" target="_blank">zelf een
kaartje te kopen</a> als dit de eerste keer is dat je meehelpt. Je krijgt een gratis kaartje voor
het jaar nádat je meegeholpen hebt.

<sup>4 -</sup> We hebben privé WhatsApp en Facebook groepen voor het Anime 2018 gopherteam.

[« Vorige pagina](index.html)
