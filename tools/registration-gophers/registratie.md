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

<span style="color: #883133">▼</span> Met wat voor shifts wil je ons komen helpen?

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
      <label for="uren">Hoeveel uur werk heeft je voorkeur?</label>
      <select name="uren" id="uren">
        <option>12 – 16 uur</option>
        <option selected>16 – 20 uur</option>
        <option>20+ uur</option>
      </select>
    </li>
    <li>
      <label for="aanwezig">Ben je het hele weekend aanwezig? <sup>1</sup></label>
      <input type="checkbox" name="aanwezig" id="aanwezig" />
    </li>
  </ol>

  <input type="submit" value="Verzenden" />
</form>

<sup>1 -</sup> We verwachten dat je tussen 10 uur 's ochtends op vrijdag en 6 uur 's avonds op
zondag aanwezig kunt zijn.

[« Vorige pagina](index.html)
